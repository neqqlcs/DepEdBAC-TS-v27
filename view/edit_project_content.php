<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Project - DepEd BAC Tracking System</title>
    <link rel="stylesheet" href="assets/css/edit_project.css">
    <link rel="stylesheet" href="assets/css/background.css">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const stageForms = document.querySelectorAll('.stage-form');
        const stageDropdown = document.getElementById('stageDropdown');
        const stageFormContainer = document.getElementById('stageFormContainer');

        // Function to save scroll position
        const saveScrollPosition = function() {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        };

        // Function to restore scroll position
        const restoreScrollPosition = function() {
            const savedPosition = sessionStorage.getItem('scrollPosition');
            if (savedPosition) {
                window.scrollTo(0, parseInt(savedPosition));
                sessionStorage.removeItem('scrollPosition'); // Clean up after use
            }
        };

        // Restore scroll position on page load
        restoreScrollPosition();

        // Highlight the current active stage for better visibility
        const highlightActiveStage = function() {
            const firstUnsubmittedStageName = <?php echo json_encode($firstUnsubmittedStageName); ?>;
            if (firstUnsubmittedStageName) {
                document.querySelectorAll(`tr[data-stage="${firstUnsubmittedStageName}"]`).forEach(row => {
                    row.style.backgroundColor = '#f8f9fa';
                    row.style.boxShadow = '0 0 5px rgba(0,0,0,0.1)';
                });

                document.querySelectorAll(`.stage-card h4`).forEach(heading => {
                    if (heading.textContent === firstUnsubmittedStageName) {
                        heading.closest('.stage-card').style.backgroundColor = '#f8f9fa';
                        heading.closest('.stage-card').style.boxShadow = '0 0 8px rgba(0,0,0,0.15)';
                    }
                });
            }
        };

        highlightActiveStage();

        // Handle stage dropdown change
        if (stageDropdown) {
            stageDropdown.addEventListener('change', function() {
                const selectedStage = this.value;
                if (selectedStage) {
                    // Save scroll position before submitting
                    saveScrollPosition();
                    // Submit form to reload page with selected stage
                    document.getElementById('stageDropdownForm').submit();
                } else {
                    // Hide the stage form if no stage is selected
                    if (stageFormContainer) {
                        stageFormContainer.style.display = 'none';
                    }
                }
            });
        }

        // Improved form validation
        stageForms.forEach(form => {
            form.addEventListener('submit', function(event) {
                saveScrollPosition(); // <-- always save, even if validation fails
                const stageNameInput = form.querySelector('input[name="stageName"]');
                const stageName = stageNameInput ? stageNameInput.value : '';
                const safeStage = stageName.replace(/ /g, '_');

                const createdField = form.querySelector(`input[name="created_${safeStage}"]`);
                const approvedAtField = form.querySelector('input[name="approvedAt"]');
                const remarkField = form.querySelector('input[name="remark"]');
                const submitButton = form.querySelector('button[name="submit_stage"]');

                const buttonText = submitButton ? submitButton.textContent.trim() : '';

                if (buttonText === 'Submit' && !submitButton.disabled) {
                    let isValid = true;
                    let errorMessages = [];

                    // Validate 'Approved' field
                    if (!approvedAtField || !approvedAtField.value) {
                        isValid = false;
                        errorMessages.push("Approved Date/Time is required");
                    }

                    // Validate 'Remark' field
                    if (!remarkField || !remarkField.value.trim()) {
                        isValid = false;
                        errorMessages.push("Remarks are required");
                    }

                    // Validate 'Created' field for admins and non-PR stages
                    const isAdmin = <?php echo json_encode($isAdmin); ?>;
                    const isPurchaseRequest = (stageName === 'Purchase Request');
                    const currentCreatedValue = createdField ? createdField.value : '';

                    if (isAdmin && !isPurchaseRequest && !currentCreatedValue) {
                        isValid = false;
                        errorMessages.push("Created Date/Time is required for this stage");
                    }

                    if (!isValid) {
                        event.preventDefault();
                        alert("Please fix the following errors:\n• " + errorMessages.join("\n• "));
                    } else {
                        // Save scroll position before form submission
                        saveScrollPosition();
                    }
                }
            });
        });

        // Add event listener for project header form submission
        const projectHeaderForm = document.querySelector('form[name="update_project_header"], form:has(button[name="update_project_header"])');
        if (projectHeaderForm) {
            projectHeaderForm.addEventListener('submit', function() {
                saveScrollPosition();
            });
        }

        // Add tooltips for better usability
        const addTooltip = function(element, text) {
            element.title = text;
            element.style.cursor = 'help';
        };

        document.querySelectorAll('th').forEach(th => {
            if (th.textContent === 'Created') {
                addTooltip(th, 'When the document was created');
            } else if (th.textContent === 'Approved') {
                addTooltip(th, 'When the document was approved');
            } else if (th.textContent === 'Office') {
                addTooltip(th, 'Office responsible for this stage');
            }
        });

        // Smooth scroll to stage form when a stage is selected
        const selectedStage = <?php echo json_encode($_POST['stageName'] ?? ''); ?>;
        if (selectedStage && stageFormContainer) {
            // Small delay to ensure the content is rendered
            setTimeout(function() {
                stageFormContainer.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 100);
        }
    });
    </script>

    <style>
    .stage-dropdown-container {
        margin: 20px 0;
        padding: 20px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        position: relative;
    }

    .stage-dropdown-container label {
        font-weight: bold;
        margin-bottom: 10px;
        display: block;
    }

    .stage-dropdown-container select {
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 100%;
        max-width: 300px;
    }

    .stage-form-container {
        margin-top: 20px;
        padding: 20px;
        background: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        scroll-margin-top: 20px; /* Add space when scrolling to this element */
    }

    .stage-form-container .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }

    .stage-form-container .form-group {
        flex: 1;
        min-width: 200px;
    }

    .stage-form-container label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .stage-form-container input[type="datetime-local"],
    .stage-form-container input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .readonly-office-field {
        padding: 8px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        color: #6c757d;
    }

    .submit-stage-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .submit-stage-btn:not(:disabled) {
        background: #28a745;
        color: white;
    }

    .submit-stage-btn:not(:disabled):hover {
        background: #218838;
    }

    .submit-stage-btn:disabled {
        background: #6c757d;
        color: white;
        cursor: not-allowed;
    }

    .submit-stage-btn.available {
        background: #17a2b8;
        color: white;
    }

    .submit-stage-btn.completed {
        background: #28a745;
        color: white;
    }

    .submit-stage-btn.autofilled {
        background: #6f42c1;
        color: white;
    }

    .submit-stage-btn.unsubmit-btn {
        background: #dc3545;
        color: white;
    }

    .submit-stage-btn.unsubmit-btn:hover {
        background: #c82333;
    }

    #stagesTable tbody tr[data-stage] {
        transition: background-color 0.3s ease;
    }

    #stagesTable tbody tr[data-stage]:hover {
        background-color: #f5f5f5;
    }

    .sequential-info {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    </style>
</head>
<body class="<?php echo $isAdmin ? 'admin-view' : 'user-view'; ?>">
    <?php
    include 'header.php'; // Uncomment if you have a header.php file
    ?>

    <div class="dashboard-container">
        <a href="<?php echo url('index.php'); ?>" class="back-btn">&larr; Back to Dashboard</a>

        <h1>Edit Project</h1>

        <?php
            if (isset($errorHeader)) { echo "<p style='color:red;'>$errorHeader</p>"; }
            if (isset($successHeader)) { echo "<p style='color:green;'>$successHeader</p>"; }
            if (isset($stageError)) { echo "<p style='color:red;'>$stageError</p>"; }
        ?>

        <div class="project-header">
            <label for="prNumber">PR Number:</label>
            <?php if ($isAdmin): ?>
                <form action="edit_project.php?projectID=<?php echo $projectID; ?>" method="post" style="margin-bottom:10px;">
                    <input type="text" name="prNumber" id="prNumber" value="<?php echo htmlspecialchars($project['prNumber']); ?>" required>
            <?php else: ?>
                <div class="readonly-field"><?php echo htmlspecialchars($project['prNumber']); ?></div>
            <?php endif; ?>

            <label for="projectDetails">Project Details:</label>
            <?php if ($isAdmin): ?>
                <textarea name="projectDetails" id="projectDetails" rows="3" required><?php echo htmlspecialchars($project['projectDetails']); ?></textarea>
            <?php else: ?>
                <div class="readonly-field"><?php echo htmlspecialchars($project['projectDetails']); ?></div>
            <?php endif; ?>
            
            <label>Mode of Procurement:</label>
            <div class="readonly-field">
                <?php echo htmlspecialchars($project['MoPDescription'] ?? 'N/A'); ?>
            </div>

            <label>Program Owner:</label>
            <p>
                <?php
                    echo htmlspecialchars($project['programOwner'] ?? 'N/A');
                    if (!empty($project['programOffice'])) {
                        echo " | Office: " . htmlspecialchars($project['programOffice']);
                    }
                ?>
            </p>

            <label for="totalABC">Total ABC:</label>
            <?php if ($isAdmin): ?>
                <input type="number" name="totalABC" id="totalABC"
                    value="<?php echo htmlspecialchars($project['totalABC']); ?>"
                    required min="0" step="1">
            <?php else: ?>
                <div class="readonly-field">
                    <?php echo isset($project['totalABC']) ? number_format($project['totalABC']) : 'N/A'; ?>
                </div>
            <?php endif; ?>
            
            <label>Created By:</label>
            <p><?php echo htmlspecialchars($project['creator_firstname'] . " " . $project['creator_lastname'] . " | Office: " . ($project['officename'] ?? 'N/A')); ?></p>

            <label>Date Created:</label>
            <p><?php echo date("m-d-Y h:i A", strtotime($project['createdAt'])); ?></p>

            <label>Last Updated:</label> <p>
                <?php
                $lastUpdatedInfo = "Not Available";
                $mostRecentTimestamp = null;
                $mostRecentUserId = null;

                $editedTs = !empty($project['editedAt']) ? strtotime($project['editedAt']) : 0;
                $lastAccessedTs = !empty($project['lastAccessedAt']) ? strtotime($project['lastAccessedAt']) : 0;

                if ($editedTs > 0 && ($editedTs >= $lastAccessedTs || $lastAccessedTs === 0)) {
                    $mostRecentTimestamp = $editedTs;
                    $mostRecentUserId = $project['editedBy'];
                } else if ($lastAccessedTs > 0) {
                    $mostRecentTimestamp = $lastAccessedTs;
                    $mostRecentUserId = $project['lastAccessedBy'];
                }

                $lastUpdatedUserFullName = "N/A";
                if (!empty($mostRecentUserId)) {
                    $stmtUser = $pdo->prepare("SELECT firstname, lastname FROM tbluser WHERE userID = ?");
                    $stmtUser->execute([$mostRecentUserId]);
                    $user = $stmtUser->fetch();
                    if ($user) {
                        $lastUpdatedUserFullName = htmlspecialchars($user['firstname'] . " " . $user['lastname']);
                    }
                }

                if ($lastUpdatedUserFullName !== "N/A" && $mostRecentTimestamp) {
                    $lastUpdatedInfo = $lastUpdatedUserFullName . ", on " . date("m-d-Y h:i A", $mostRecentTimestamp);
                }
                echo $lastUpdatedInfo;
                ?>
            </p>
            <?php if ($isAdmin): ?>
                <button type="submit" name="update_project_header" class="update-project-details-btn">
                    <span>Update Project Details</span>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <h3>Project Stages</h3>
        <?php
            $projectStatusClass = $noticeToProceedSubmitted ? 'finished' : 'in-progress';
            $projectStatusText = 'Status: ' . ($noticeToProceedSubmitted ? 'Finished' : 'In Progress');
            echo '<div class="project-status ' . $projectStatusClass . '">' . $projectStatusText . '</div>';
        ?>
        <?php if (isset($stageSuccess)) { echo "<p style='color:green;'>$stageSuccess</p>"; } ?>

        <!-- Information about stage processing -->
        <div class="sequential-info">
            <strong>Stage Processing:</strong> All stages are available for processing.
        </div>

        <!-- Stage Selection Dropdown (always visible) -->
        <div class="stage-dropdown-container" id="stageDropdownSection">
            <form id="stageDropdownForm" method="post" action="">
                <label for="stageDropdown"><strong>Select Stage to Process:</strong></label>
                <select id="stageDropdown" name="stageName" onchange="this.form.submit()">
                    <option value="">-- Select a Stage --</option>
                    <?php foreach ($unsubmittedStages as $stage): ?>
                        <option value="<?php echo htmlspecialchars($stage); ?>"
                            <?php if (isset($_POST['stageName']) && $_POST['stageName'] === $stage) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($stage); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Process Stage Card (only if a stage is selected) -->
        <?php
        $selectedStage = $_POST['stageName'] ?? '';
        if ($selectedStage && in_array($selectedStage, $unsubmittedStages)):
            $safeStage = str_replace(' ', '_', $selectedStage);
            $currentStageData = $stagesMap[$selectedStage] ?? null;
            $value_created = ($currentStageData && !empty($currentStageData['createdAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['createdAt'])) : "";
            $value_approved = ($currentStageData && !empty($currentStageData['approvedAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['approvedAt'])) : "";
            $value_remark = ($currentStageData && !empty($currentStageData['remarks'])) ? htmlspecialchars($currentStageData['remarks']) : "";
            $displayOfficeName = $loggedInUserOfficeName;
        ?>
        <div id="stageFormContainer" class="stage-form-container">
            <h4>Process Stage: <?php echo htmlspecialchars($selectedStage); ?></h4>
            <form action="" method="post" class="stage-form">
                <input type="hidden" name="stageName" value="<?php echo htmlspecialchars($selectedStage); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Created At:</label>
                        <input type="datetime-local" name="created_<?php echo $safeStage; ?>"
                            value="<?php echo $value_created; ?>"
                            <?php if (!$isAdmin || $selectedStage === 'Purchase Request') echo "disabled"; ?>
                            <?php if ($isAdmin && $selectedStage !== 'Purchase Request') echo "required"; ?>>
                    </div>
                    <div class="form-group">
                        <label>Approved At: <span style="color: red;">*</span></label>
                        <input type="datetime-local" name="approvedAt"
                            value="<?php echo $value_approved; ?>"
                            required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Office:</label>
                        <div class="readonly-office-field"><?php echo $displayOfficeName; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Remark: <span style="color: red;">*</span></label>
                        <input type="text" name="remark" value="<?php echo $value_remark; ?>" 
                            placeholder="Enter remarks for this stage">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="submit_stage" class="submit-stage-btn">Submit Stage</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Static Table of All Stages (always visible, below the card) -->
        <div class="table-wrapper">
            <table id="stagesTable">
                <thead>
                    <tr>
                        <th style="width: 15%;">Stage</th>
                        <th style="width: 20%;">Created</th>
                        <th style="width: 20%;">Approved</th>
                        <th style="width: 15%;">Office</th>
                        <th style="width: 15%;">Remark</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Display all stages in order with their current status
                    foreach ($stagesOrder as $index => $stage):
                        $safeStage = str_replace(' ', '_', $stage);
                        $currentStageData = $stagesMap[$stage] ?? null;

                        // Special handling for Mode Of Procurement
                        if ($stage === 'Mode Of Procurement'): ?>
                            <tr data-stage="<?php echo htmlspecialchars($stage); ?>">
                                <td><?php echo htmlspecialchars($stage); ?></td>
                                <td colspan="4">
                                    <div class="readonly-field">
                                        <?php echo htmlspecialchars($project['MoPDescription'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="submit-stage-btn autofilled" disabled>Autofilled</button>
                                </td>
                            </tr>
                            <?php continue; ?>
                        <?php endif;

                        $currentSubmitted = ($currentStageData && $currentStageData['isSubmitted'] == 1);

                        $value_created = ($currentStageData && !empty($currentStageData['createdAt']))
                                                ? date("Y-m-d\TH:i", strtotime($currentStageData['createdAt'])) : "";
                        $value_approved = ($currentStageData && !empty($currentStageData['approvedAt']))
                                                ? date("Y-m-d\TH:i", strtotime($currentStageData['approvedAt'])) : "";

                        $value_remark = ($currentStageData && !empty($currentStageData['remarks']))
                                                ? htmlspecialchars($currentStageData['remarks']) : "";

                        // FIXED: Only show office information for submitted stages
                        $displayOfficeName = "Not set";
                        if ($currentSubmitted && isset($currentStageData['officeID']) && isset($officeList[$currentStageData['officeID']])) {
                            $displayOfficeName = htmlspecialchars($currentStageData['officeID'] . ' - ' . $officeList[$currentStageData['officeID']]);
                        }
                    ?>
                    <tr data-stage="<?php echo htmlspecialchars($stage); ?>">
                        <td><?php echo htmlspecialchars($stage); ?></td>
                        <td>
                            <?php if ($value_created): ?>
                                <input type="datetime-local" value="<?php echo $value_created; ?>" disabled>
                            <?php else: ?>
                                <span style="color: #6c757d;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($value_approved): ?>
                                <input type="datetime-local" value="<?php echo $value_approved; ?>" disabled>
                            <?php else: ?>
                                <span style="color: #6c757d;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($currentSubmitted): ?>
                                <div class="readonly-office-field">
                                    <?php echo $displayOfficeName; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #6c757d;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($value_remark): ?>
                                <input type="text" value="<?php echo $value_remark; ?>" disabled>
                            <?php else: ?>
                                <span style="color: #6c757d;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($currentSubmitted) {
                                echo '<button type="button" class="submit-stage-btn completed" disabled>Submitted</button>';
                            } else {
                                echo '<button type="button" class="submit-stage-btn available">Available</button>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($noticeToProceedSubmitted): ?>
            <div class="completion-message">
                <p>All stages are completed! This project is now finished.</p>
            </div>
        <?php endif; ?>

        <div class="card-view">
            <?php foreach ($stagesOrder as $index => $stage):
                if ($stage === 'Mode Of Procurement') continue;
                
                $safeStage = str_replace(' ', '_', $stage);
                $currentStageData = $stagesMap[$stage] ?? null;
                $currentSubmitted = ($currentStageData && $currentStageData['isSubmitted'] == 1);

                $value_created = ($currentStageData && !empty($currentStageData['createdAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['createdAt'])) : "";
                $value_approved = ($currentStageData && !empty($currentStageData['approvedAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['approvedAt'])) : "";
                $value_remark = ($currentStageData && !empty($currentStageData['remarks'])) ? htmlspecialchars($currentStageData['remarks']) : "";

                // FIXED: Only show office information for submitted stages
                $displayOfficeName = "Not set";
                if ($currentSubmitted && isset($currentStageData['officeID']) && isset($officeList[$currentStageData['officeID']])) {
                    $displayOfficeName = htmlspecialchars($currentStageData['officeID'] . ' - ' . $officeList[$currentStageData['officeID']]);
                }
            ?>
            <div class="stage-card">
                <h4><?php echo htmlspecialchars($stage); ?></h4>

                <label>Created At:</label>
                <input type="datetime-local" value="<?php echo $value_created; ?>" disabled>

                <label>Approved At:</label>
                <input type="datetime-local" value="<?php echo $value_approved; ?>" disabled>

                <label>Office:</label>
                <?php if ($currentSubmitted): ?>
                    <div class="readonly-office-field">
                        <?php echo $displayOfficeName; ?>
                    </div>
                <?php else: ?>
                    <span style="color: #6c757d;">Not set</span>
                <?php endif; ?>

                <label>Remark:</label>
                <input type="text" value="<?php echo $value_remark; ?>" disabled>

                <div style="margin-top:10px;">
                    <?php
                    if ($currentSubmitted) {
                        echo '<button type="button" class="submit-stage-btn completed" disabled>Submitted</button>';
                    } else {
                        echo '<button type="button" class="submit-stage-btn available">Available</button>';
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card-view">
            <?php foreach ($stagesOrder as $index => $stage):
                if ($stage === 'Mode Of Procurement') continue;
                
                $safeStage = str_replace(' ', '_', $stage);
                $currentStageData = $stagesMap[$stage] ?? null;
                $currentSubmitted = ($currentStageData && $currentStageData['isSubmitted'] == 1);

                $value_created = ($currentStageData && !empty($currentStageData['createdAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['createdAt'])) : "";
                $value_approved = ($currentStageData && !empty($currentStageData['approvedAt'])) ? date("Y-m-d\TH:i", strtotime($currentStageData['approvedAt'])) : "";
                $value_remark = ($currentStageData && !empty($currentStageData['remarks'])) ? htmlspecialchars($currentStageData['remarks']) : "";

                // FIXED: Only show office information for submitted stages
                $displayOfficeName = "Not set";
                if ($currentSubmitted && isset($currentStageData['officeID']) && isset($officeList[$currentStageData['officeID']])) {
                    $displayOfficeName = htmlspecialchars($currentStageData['officeID'] . ' - ' . $officeList[$currentStageData['officeID']]);
                }
            ?>
            <div class="stage-card">
                <h4><?php echo htmlspecialchars($stage); ?></h4>

                <label>Created At:</label>
                <input type="datetime-local" value="<?php echo $value_created; ?>" disabled>

                <label>Approved At:</label>
                <input type="datetime-local" value="<?php echo $value_approved; ?>" disabled>

                <label>Office:</label>
                <?php if ($currentSubmitted): ?>
                    <div class="readonly-office-field">
                        <?php echo $displayOfficeName; ?>
                    </div>
                <?php else: ?>
                    <span style="color: #6c757d;">Not set</span>
                <?php endif; ?>

                <label>Remark:</label>
                <input type="text" value="<?php echo $value_remark; ?>" disabled>

                <div style="margin-top:10px;">
                    <?php
                    if ($currentSubmitted) {
                        echo '<button type="button" class="submit-stage-btn completed" disabled>Submitted</button>';
                    } else {
                        echo '<button type="button" class="submit-stage-btn available">Available</button>';
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
</body>
</html>