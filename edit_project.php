<?php
// Start the session if it hasn't been started yet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'url_helper.php';

// Check that the user is logged in.
if (!isset($_SESSION['username'])) {
    redirect('login.php');
}

// Get the projectID from GET parameters.
$projectID = isset($_GET['projectID']) ? intval($_GET['projectID']) : 0;
if ($projectID <= 0) {
    die("Invalid Project ID");
}

// Permission Variables
$isAdmin = ($_SESSION['admin'] == 1);
$isProjectCreator = false;

// --- Define the Office List (fetched dynamically) ---
$officeList = [];
try {
    $stmtOffice = $pdo->query("SELECT officeID, officename FROM officeid ORDER BY officename");
    while ($row = $stmtOffice->fetch(PDO::FETCH_ASSOC)) {
        $officeList[$row['officeID']] = $row['officename'];
    }
} catch (PDOException $e) {
    error_log("Error fetching office list: " . $e->getMessage());
    die("Could not retrieve office list. Please try again later.");
}

// --- Get the logged-in user's office details ---
$loggedInUserOfficeID = null;
$loggedInUserOfficeName = "N/A";
if (isset($_SESSION['userID'])) {
    try {
        $stmtUserOffice = $pdo->prepare("SELECT u.officeID, o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID WHERE u.userID = ?");
        $stmtUserOffice->execute([$_SESSION['userID']]);
        $userOfficeData = $stmtUserOffice->fetch(PDO::FETCH_ASSOC);
        if ($userOfficeData) {
            $loggedInUserOfficeID = $userOfficeData['officeID'];
            $loggedInUserOfficeName = htmlspecialchars($userOfficeData['officeID'] . ' - ' . ($userOfficeData['officename'] ?? 'N/A'));
        }
    } catch (PDOException $e) {
        error_log("Error fetching logged-in user office details: " . $e->getMessage());
    }
}

// --- Function to fetch project details ---
function fetchProjectDetails($pdo, $projectID) {
    $sql = "
        SELECT
            p.*,
            u.firstname AS creator_firstname,
            u.lastname AS creator_lastname,
            o.officename,
            mop.MoPDescription
        FROM tblproject p
        LEFT JOIN tbluser u ON p.userID = u.userID
        LEFT JOIN officeid o ON u.officeID = o.officeID
        LEFT JOIN mode_of_procurement mop ON p.MoPID = mop.MoPID
        WHERE p.projectID = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$projectID]);
    return $stmt->fetch();
}

// --- Function to fetch project stages (ordered by stageID) ---
function fetchProjectStages($pdo, $projectID) {
    $stmt2 = $pdo->prepare("SELECT * FROM tblproject_stages WHERE projectID = ? ORDER BY stageID ASC");
    $stmt2->execute([$projectID]);
    $stages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // If no stages exist, create default stages (should not happen if project creation is correct)
    if (empty($stages)) {
        // Fetch default stages from stage_reference table
        $stmtRef = $pdo->query("SELECT stageName FROM stage_reference ORDER BY stageOrder ASC");
        $defaultStages = $stmtRef->fetchAll(PDO::FETCH_COLUMN);

        foreach ($defaultStages as $stageName) {
            $insertCreatedAt = ($stageName === 'Purchase Request') ? date("Y-m-d H:i:s") : null;
            $stmtInsert = $pdo->prepare("INSERT INTO tblproject_stages (projectID, stageName, officeID, createdAt) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$projectID, $stageName, null, $insertCreatedAt]);
        }
        // Re-fetch stages after creation
        $stmt2->execute([$projectID]);
        $stages = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
    return $stages;
}

// --- Initial Data Fetch ---
$project = fetchProjectDetails($pdo, $projectID);
if (!$project) {
    die("Project not found");
}
$isProjectCreator = ($project['userID'] == $_SESSION['userID']);

// Fetch stage order from reference table
$stmtStageRef = $pdo->query("SELECT stageName FROM stage_reference ORDER BY stageOrder ASC");
$stagesOrder = $stmtStageRef->fetchAll(PDO::FETCH_COLUMN);

// Exclude "Mode Of Procurement" from submittable stages
$submittableStages = array_filter($stagesOrder, function($stage) {
    return $stage !== 'Mode Of Procurement';
});

// Fetch all stages for this project, ordered by stageID
$stages = fetchProjectStages($pdo, $projectID);

// Map stages by stageName for easy access and find the last submitted stage.
$stagesMap = [];
$noticeToProceedSubmitted = false;
$lastSubmittedStageIndex = -1;

foreach ($stagesOrder as $index => $stageName) {
    $s = null;
    foreach ($stages as $stage) {
        if ($stage['stageName'] === $stageName) {
            $s = $stage;
            break;
        }
    }
    if ($s) {
        $stagesMap[$stageName] = $s;
        if ($s['isSubmitted'] == 1) {
            $lastSubmittedStageIndex = $index;
        }
        if ($stageName === 'Notice to Proceed' && $s['isSubmitted'] == 1) {
            $noticeToProceedSubmitted = true;
        }
    }
}
$lastSubmittedStageName = ($lastSubmittedStageIndex !== -1) ? $stagesOrder[$lastSubmittedStageIndex] : null;

// Process Project Header update (available ONLY for admins).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_header'])) {
    if ($isAdmin) {
        $prNumber = trim($_POST['prNumber']);
        $projectDetails = trim($_POST['projectDetails']);
        if (empty($prNumber) || empty($projectDetails)) {
            $errorHeader = "PR Number and Project Details are required.";
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE tblproject
                                             SET prNumber = ?, projectDetails = ?, editedAt = CURRENT_TIMESTAMP, editedBy = ?, lastAccessedAt = CURRENT_TIMESTAMP, lastAccessedBy = ?
                                             WHERE projectID = ?");
            $stmtUpdate->execute([$prNumber, $projectDetails, $_SESSION['userID'], $_SESSION['userID'], $projectID]);

            $successHeader = "Project details updated successfully.";
            $project = fetchProjectDetails($pdo, $projectID);
            $stages = fetchProjectStages($pdo, $projectID);
            $stagesOrder = array_column($stages, 'stageName');
        }
    } else {
        $errorHeader = "You do not have permission to update project details.";
    }
}

// Process individual stage submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_stage'])) {
    $stageName = $_POST['stageName'];
    $safeStage = str_replace(' ', '_', $stageName);

    $currentStageDataForPost = $stagesMap[$stageName] ?? null;
    $currentIsSubmittedInDB = ($currentStageDataForPost && $currentStageDataForPost['isSubmitted'] == 1);

    $formCreated = isset($_POST["created_$safeStage"]) && !empty($_POST["created_$safeStage"]) ? $_POST["created_$safeStage"] : null;
    $approvedAt = isset($_POST['approvedAt']) && !empty($_POST['approvedAt']) ? $_POST['approvedAt'] : null;
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : "";

    // Determine if this is a "Submit" or "Unsubmit" action
    $isSubmittedVal = 1; // Default to submit
    if ($isAdmin && $currentIsSubmittedInDB && $stageName === $lastSubmittedStageName) {
        $isSubmittedVal = 0;
    }

    // --- Validation Logic ---
    $validationFailed = false;
    if ($isSubmittedVal == 1) { // Only validate on "Submit" action
        if (empty($approvedAt)) {
            $validationFailed = true;
        }
        if ($isAdmin && $stageName !== 'Purchase Request' && empty($formCreated)) {
            $validationFailed = true;
        }
    }

    if ($validationFailed) {
        $stageError = "All required fields (Approved" . ($isAdmin && $stageName !== 'Purchase Request' ? ", Created" : "") . ") must be filled for stage '$stageName' to be submitted.";
    } else {
        $officeIDToSave = $loggedInUserOfficeID;
        $currentCreatedAtInDB = $currentStageDataForPost['createdAt'] ?? null;
        $actualCreatedAt = $currentCreatedAtInDB;

        if ($isAdmin) {
            if ($stageName !== 'Purchase Request' && !empty($formCreated)) {
                $actualCreatedAt = $formCreated;
            } else if ($isSubmittedVal == 1 && empty($currentCreatedAtInDB) && $stageName !== 'Purchase Request') {
                $actualCreatedAt = date("Y-m-d H:i:s");
            }
        } else {
            if ($isSubmittedVal == 1 && empty($currentCreatedAtInDB)) {
                $actualCreatedAt = date("Y-m-d H:i:s");
            }
        }

        $created_dt = $actualCreatedAt ? date("Y-m-d H:i:s", strtotime($actualCreatedAt)) : null;

        if ($isSubmittedVal == 0) {
            $approved_dt = null;
            $officeIDToSave = null;
            $remark = "";
        } else {
            $approved_dt = $approvedAt ? date("Y-m-d H:i:s", strtotime($approvedAt)) : null;
        }

        $stmtStageUpdate = $pdo->prepare("UPDATE tblproject_stages
                                               SET createdAt = ?, approvedAt = ?, officeID = ?, remarks = ?, isSubmitted = ?
                                               WHERE projectID = ? AND stageName = ?");
        $stmtStageUpdate->execute([$created_dt, $approved_dt, $officeIDToSave, $remark, $isSubmittedVal, $projectID, $stageName]);
        $stageSuccess = "Stage '$stageName' updated successfully.";

        // Update editedAt/editedBy for tblproject
        $pdo->prepare("UPDATE tblproject SET editedAt = CURRENT_TIMESTAMP, editedBy = ?, lastAccessedAt = CURRENT_TIMESTAMP, lastAccessedBy = ? WHERE projectID = ?")
            ->execute([$_SESSION['userID'], $_SESSION['userID'], $projectID]);

        // Re-fetch ALL data immediately after stage update/submission
        $project = fetchProjectDetails($pdo, $projectID);
        $stages = fetchProjectStages($pdo, $projectID);
        $stagesOrder = array_column($stages, 'stageName');

        // Re-map stages after re-fetching to ensure latest status is used for rendering
        $stagesMap = [];
        $noticeToProceedSubmitted = false;
        $lastSubmittedStageIndex = -1;
        foreach ($stages as $index => $s) {
            $stagesMap[$s['stageName']] = $s;
            if ($s['isSubmitted'] == 1) {
                $stageIndexInOrder = array_search($s['stageName'], $stagesOrder);
                if ($stageIndexInOrder !== false && $stageIndexInOrder > $lastSubmittedStageIndex) {
                    $lastSubmittedStageIndex = $stageIndexInOrder;
                }
            }
            if ($s['stageName'] === 'Notice to Proceed' && $s['isSubmitted'] == 1) {
                $noticeToProceedSubmitted = true;
            }
        }
        $lastSubmittedStageName = ($lastSubmittedStageIndex !== -1) ? $stagesOrder[$lastSubmittedStageIndex] : null;
    }
}

// --- Pre-fetch names for display: Edited By ---
$editedByName = "N/A";
if (!empty($project['editedBy'])) {
    $stmtUser = $pdo->prepare("SELECT firstname, lastname FROM tbluser WHERE userID = ?");
    $stmtUser->execute([$project['editedBy']]);
    $user = $stmtUser->fetch();
    if ($user) {
        $editedByName = htmlspecialchars($user['firstname'] . " " . $user['lastname']);
    }
}

// Get all unsubmitted stages except "Mode Of Procurement"
$unsubmittedStages = [];
foreach ($stagesOrder as $stage) {
    if ($stage === 'Mode Of Procurement') continue;
    if (isset($stagesMap[$stage]) && $stagesMap[$stage]['isSubmitted'] == 0) {
        $unsubmittedStages[] = $stage;
    }
}

// --- Determine the "Next Unsubmitted Stage" for strict sequential access ---
$firstUnsubmittedStageName = null;
foreach ($stagesOrder as $stage) {
    if ($stage === 'Mode Of Procurement') continue; // <-- skip MoP
    if (isset($stagesMap[$stage]) && $stagesMap[$stage]['isSubmitted'] == 0) {
        $firstUnsubmittedStageName = $stage;
        break;
    }
}

// After updating the stage in the database:
$stages = fetchProjectStages($pdo, $projectID); // re-fetch latest data
$stagesMap = [];
foreach ($stages as $stageRow) {
    $stagesMap[$stageRow['stageName']] = $stageRow;
}

include 'view/edit_project_content.php';
?>