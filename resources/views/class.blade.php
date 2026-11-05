<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyQuest - Class</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite([
    'resources/css/app.css',
    'resources/css/class.css',
    'resources/js/class.js'
    ])
</head>

<body>
    <header class="sq-topbar">
        <div class="sq-topbar-inner">
            <div class="sq-header-actions">
                <a class="sq-brand" href="/dashboard" aria-label="StudyQuest dashboard">
                    <img src="{{ asset('images/logo.png') }}" alt="StudyQuest logo" class="sq-brand-mark">
                    <span>StudyQuest</span>
                </a>
            </div>
            <div class="sq-topbar-actions">
                <a class="sq-back-link" href="/dashboard" aria-label="Back to dashboard">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <div class="sq-profile-area">
                    <button
                        class="sq-profile-button"
                        id="profile-button"
                        type="button"
                        aria-label="Open account menu"
                        aria-expanded="false"
                        aria-controls="profile-menu"
                    >
                        <img class="sq-profile-image" id="profile-button-image" alt="" hidden>
                        <span id="profile-initial">U</span>
                    </button>

                    <div class="sq-profile-menu" id="profile-menu" hidden>
                        <div class="sq-profile-menu-info">
                            <div class="sq-profile-menu-avatar" aria-hidden="true">
                                <img class="sq-profile-image" id="profile-menu-image" alt="" hidden>
                                <span id="profile-menu-initial">U</span>
                            </div>
                            <div class="sq-profile-menu-identity">
                                <strong id="profile-name">User</strong>
                                <span id="profile-email">Loading...</span>
                            </div>
                        </div>

                        <div class="sq-profile-menu-actions">
                            <button type="button" id="display-profile-button" class="sq-profile-menu-item">
                                <span class="sq-profile-menu-item-icon" aria-hidden="true"><i class="fa-regular fa-user"></i></span>
                                Display Profile
                            </button>
                            <button type="button" id="edit-profile-button" class="sq-profile-menu-item">
                                <span class="sq-profile-menu-item-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
                                Edit Profile
                            </button>
                        </div>

                        <button type="button" id="logout-button" class="sq-logout-button">
                            <span class="sq-profile-menu-item-icon" aria-hidden="true"><i class="fa-solid fa-right-from-bracket"></i></span>
                            Log out
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="class-page" data-class-id="{{ $classId }}" class="sq-layout">
        <section class="sq-main">
            <section class="sq-course-banner" aria-label="Class information">
                <div class="sq-course-icon" id="course-initials">IM</div>
                <div class="sq-course-details">
                    <h1 id="class-name">Loading class...</h1>
                    <p class="sq-course-description">Manage class posts, share resources, and keep your students updated.</p>
                    <div class="sq-course-meta">
                        <div class="sq-meta-item">
                            <span class="sq-meta-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                            <span>
                                <span class="sq-meta-label">Course Code</span>
                                <strong id="course-code">—</strong>
                            </span>
                        </div>
                        <div class="sq-meta-item">
                            <span class="sq-meta-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                            <span>
                                <span class="sq-meta-label">Class Code</span>
                                <strong id="class-code">—</strong>
                            </span>
                        </div>
                        <div class="sq-meta-item">
                            <span class="sq-meta-icon" aria-hidden="true"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span>
                                <span class="sq-meta-label">Year Level</span>
                                <strong id="year-level">—</strong>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="sq-course-stats" aria-label="Class activity">
                    <div class="sq-course-stat">
                        <span class="sq-stat-icon sq-stat-icon-blue" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                        <span>
                            <span class="sq-stat-label">Students</span>
                            <strong id="summary-student-count">0</strong>
                        </span>
                    </div>
                    <div class="sq-course-stat">
                        <span class="sq-stat-icon sq-stat-icon-violet" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
                        <span>
                            <span class="sq-stat-label">Posts</span>
                            <strong id="post-count">0</strong>
                        </span>
                    </div>
                </div>
            </section>

            <div class="sq-posts-heading">
                <div>
                    <h2>Posts</h2>
                    <p>Share announcements, lessons, and resources with your class.</p>
                </div>
                <button class="sq-add-post" id="add-post-button" type="button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Create Post</span>
                </button>
            </div>

            <section id="posts-list" aria-label="Class posts">
                <div class="sq-post-empty">No posts yet. Share a file or update with your class.</div>
            </section>
        </section>

        <aside class="sq-students-panel">
            <div class="sq-students-title">
                <h2>Students</h2>
                <span class="sq-student-count" id="student-count">0</span>
            </div>

            <label class="sq-search">
                <i class="fa-solid fa-magnifying-glass sq-search-icon" aria-hidden="true"></i>
                <input id="student-search" type="search" placeholder="Search students..." autocomplete="off">
            </label>

            <p id="students-loading">Loading students...</p>
            <div id="students-list"></div>
            <div id="students-empty" hidden>No students have joined this class yet.</div>
            <button class="sq-view-students" id="view-all-students" type="button" aria-expanded="false" hidden>
                <span id="view-students-label">View all students</span>
                <i id="view-students-icon" class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </aside>
    </main>

    <div class="sq-post-modal" id="post-modal" aria-hidden="true">
        <div class="sq-post-modal-card" role="dialog" aria-modal="true" aria-labelledby="post-modal-title">
            <div class="sq-post-modal-header">
                <div class="sq-post-modal-brand">
                    <div class="sq-post-modal-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></div>
                    <div>
                        <h2 id="post-modal-title">Add Post</h2>
                        <p>Share a learning material with your class.</p>
                    </div>
                </div>
                <button class="sq-post-modal-close" type="button" id="close-post-modal" aria-label="Close add post dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form class="sq-post-form" id="add-post-form">
                <div class="sq-field">
                    <label for="post-title">Post Title <span aria-hidden="true">*</span></label>
                    <input id="post-title" name="post_title" type="text" maxlength="100" placeholder="Type your post title" required>
                    <div class="sq-char-count"><span id="post-title-count">0 / 100</span></div>
                </div>

                <div class="sq-field">
                    <label for="post-description">Class Description (Optional)</label>
                    <textarea id="post-description" name="post_description" maxlength="200" placeholder="Write a short summary for your class..."></textarea>
                    <div class="sq-char-count"><span id="post-description-count">0 / 200</span></div>
                </div>

                <div class="sq-divider"></div>

                <div id="existing-attachments-section" hidden>
                    <label>Current Attachments</label>
                    <div id="existing-attachments-list"></div>
                </div>

                <div class="sq-upload-wrapper">
                    <label class="sq-upload-box" for="post-file">
                        <input id="post-file" name="post_files[]" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp" multiple>
                        <div class="sq-upload-content" id="post-upload-content">
                            <div class="sq-upload-icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                            <div class="sq-upload-text">
                                <strong>Click to upload</strong>
                                <span>PDF, DOCX, PPTX, IMG (Max 10MB each; select multiple files)</span>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="sq-selected-files" id="selected-attachments-section" hidden>
                    <label>Selected Files</label>
                    <div id="selected-attachments-list"></div>
                </div>

                <div class="sq-post-actions">
                    <button class="sq-cancel-button" type="button" id="cancel-post-modal">Cancel</button>
                    <button class="sq-submit-post" id="submit-post-button" type="submit">
                        + Add Post
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="sq-post-modal sq-remove-student-modal" id="remove-student-modal" aria-hidden="true">
        <div class="sq-post-modal-card sq-remove-student-modal-card" role="dialog" aria-modal="true" aria-labelledby="remove-student-modal-title">
            <div class="sq-post-modal-header">
                <div class="sq-post-modal-brand">
                    <div class="sq-post-modal-icon sq-remove-student-modal-icon" aria-hidden="true">
                        <i class="fa-solid fa-user-minus"></i>
                    </div>
                    <div>
                        <h2 id="remove-student-modal-title">Remove student?</h2>
                        <p>This student will lose access to this class.</p>
                    </div>
                </div>
                <button class="sq-post-modal-close" type="button" id="close-remove-student-modal" aria-label="Close remove student dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="sq-remove-student-modal-body">
                <p class="sq-remove-student-prompt">You’re about to remove</p>
                <div class="sq-remove-student-identity">
                    <span class="sq-remove-student-identity-icon" aria-hidden="true">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <strong id="remove-student-name"></strong>
                </div>
                <div class="sq-remove-student-warning">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>They won’t be able to view class posts or materials unless they rejoin.</p>
                </div>
                <p id="remove-student-error" class="sq-remove-student-error" role="alert" hidden>
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    Could not remove this student. Please try again.
                </p>
            </div>

            <div class="sq-post-actions">
                <button class="sq-cancel-button" type="button" id="cancel-remove-student">Cancel</button>
                <button class="sq-remove-student-confirm" type="button" id="confirm-remove-student">Remove student</button>
            </div>
        </div>
    </div>

    <div class="sq-post-modal sq-remove-student-modal sq-delete-announcement-modal" id="delete-announcement-modal" aria-hidden="true">
        <div class="sq-post-modal-card sq-remove-student-modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-announcement-modal-title">
            <div class="sq-post-modal-header">
                <div class="sq-post-modal-brand">
                    <div class="sq-post-modal-icon sq-remove-student-modal-icon" aria-hidden="true">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                    <div>
                        <h2 id="delete-announcement-modal-title">Delete announcement?</h2>
                        <p>This action can’t be undone.</p>
                    </div>
                </div>
                <button class="sq-post-modal-close" type="button" id="close-delete-announcement-modal" aria-label="Close delete announcement dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="sq-remove-student-modal-body">
                <p class="sq-remove-student-prompt">You’re about to delete</p>
                <div class="sq-remove-student-identity">
                    <span class="sq-remove-student-identity-icon" aria-hidden="true">
                        <i class="fa-solid fa-file-lines"></i>
                    </span>
                    <strong id="delete-announcement-title"></strong>
                </div>
                <div class="sq-remove-student-warning">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>This announcement and its attachments will be removed from the class.</p>
                </div>
                <p id="delete-announcement-error" class="sq-remove-student-error" role="alert" hidden>
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>Could not delete this announcement. Please try again.</span>
                </p>
            </div>

            <div class="sq-post-actions">
                <button class="sq-cancel-button" type="button" id="cancel-delete-announcement">Cancel</button>
                <button class="sq-remove-student-confirm" type="button" id="confirm-delete-announcement">Delete announcement</button>
            </div>
        </div>
    </div>

</body>

</html>