<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyQuest - Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <div class="dashboard-header-inner">
            <a href="/dashboard" class="dashboard-brand">
                <img src="{{ asset('images/logo.png') }}" alt="StudyQuest logo">
                <span>StudyQuest</span>
            </a>

            <div class="profile-area">
                <button
                    class="profile-button"
                    id="profile-button"
                    type="button"
                    aria-label="Open account menu"
                    aria-expanded="false"
                    aria-controls="profile-menu"
                >
                    <img class="profile-avatar-image" id="profile-button-image" alt="" hidden>
                    <span id="profile-initial">T</span>
                </button>

                <div class="profile-menu" id="profile-menu" hidden>
                    <div class="profile-menu-info">
                        <div class="profile-menu-avatar" aria-hidden="true">
                            <img class="profile-avatar-image" id="profile-menu-image" alt="" hidden>
                            <span id="profile-menu-initial">T</span>
                        </div>
                        <div class="profile-menu-identity">
                            <strong id="profile-name">Teacher</strong>
                            <span id="profile-email">Loading...</span>
                        </div>
                    </div>

                    <div class="profile-menu-actions">
                        <button type="button" id="display-profile-button" class="profile-menu-item">
                            <span class="profile-menu-item-icon" aria-hidden="true"><i class="fa-regular fa-user"></i></span>
                            Display Profile
                        </button>

                        <button type="button" id="edit-profile-button" class="profile-menu-item">
                            <span class="profile-menu-item-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
                            Edit Profile
                        </button>
                    </div>

                    <button type="button" id="logout-button" class="logout-button">
                        <span class="profile-menu-item-icon" aria-hidden="true"><i class="fa-solid fa-right-from-bracket"></i></span>
                        Log out
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="dashboard-main">
        <section class="dashboard-hero" aria-labelledby="dashboard-title">
            <div class="dashboard-hero-copy">
                <span class="dashboard-eyebrow">Teacher Mode</span>
                <h1 id="dashboard-title">Your Classes</h1>
                <p>Manage your classes, view student details, and create posts.</p>
            </div>
            <div class="dashboard-hero-art" aria-hidden="true">
                <i class="fa-solid fa-graduation-cap dashboard-hero-cap"></i>
                <i class="fa-solid fa-book-open dashboard-hero-book"></i>
                <i class="fa-solid fa-star dashboard-hero-sparkle dashboard-hero-sparkle-one"></i>
                <i class="fa-solid fa-star dashboard-hero-sparkle dashboard-hero-sparkle-two"></i>
            </div>
        </section>

        <div class="dashboard-toolbar">
            <div class="class-filters">
                <label class="class-search" for="class-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="class-search" type="search" placeholder="Search classes..." autocomplete="off">
                </label>
                <label class="year-filter" for="year-level-filter">
                    <span class="sr-only">Filter classes by year level</span>
                    <select id="year-level-filter">
                        <option value="">All Year Levels</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </label>
            </div>

            <div class="dashboard-toolbar-actions">
                <div class="class-view-toggle" role="group" aria-label="Class layout">
                    <button type="button" id="grid-view-button" class="is-active" aria-label="Grid view" aria-pressed="true">
                        <i class="fa-solid fa-grip" aria-hidden="true"></i>
                    </button>
                    <button type="button" id="list-view-button" aria-label="List view" aria-pressed="false">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="button" class="add-class-button" id="open-class-modal">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Add Class</span>
                </button>
            </div>
        </div>

        <p class="class-results-count" id="class-results-count" aria-live="polite"></p>
        <div class="classes-grid" id="classes-grid">
            <div class="classes-loading" id="classes-loading">
                Loading your classes...
            </div>
        </div>

        <div class="no-class-results" id="no-class-results" hidden>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <p>No classes match your search.</p>
        </div>

        <div class="empty-classes" id="empty-classes" hidden>
            <div class="empty-classes-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></div>
            <h2>No classes yet</h2>
            <p>Create your first class to get started.</p>
            <button type="button" class="add-class-button" id="empty-add-class">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add class
            </button>
        </div>
    </main>

    <div class="modal-backdrop" id="class-modal" hidden>
        <section
            class="class-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="class-modal-title"
        >
            <button
                type="button"
                class="modal-close"
                id="close-class-modal"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <div class="modal-heading">
                <div class="modal-icon" aria-hidden="true">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <h2 id="class-modal-title">Add New Class</h2>
                    <p>
                        Generate a new class and a unique class code will be
                        generated automatically.
                    </p>
                </div>
            </div>

            <form id="add-class-form" class="class-form">
                <h3>Class Information</h3>

                <label for="class-name">
                    Class Name <span>*</span>
                </label>
                <input
                    id="class-name"
                    name="class_name"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. Information Management 2"
                    required
                >
                <div class="character-count">
                    <span>Use a simple and clear name for your class code</span>
                    <span id="class-name-count">0 / 100</span>
                </div>

                <div class="form-two-columns">
                    <div>
                        <label for="subject-code">
                            Subject Code <span>*</span>
                        </label>
                        <input
                            id="subject-code"
                            name="subject_code"
                            type="text"
                            maxlength="30"
                            placeholder="e.g. C316"
                            required
                        >
                    </div>

                    <div>
                        <label for="year-level">
                            Year Level <span>*</span>
                        </label>
                        <select id="year-level" name="year_level" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year" selected>3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                </div>

                <label for="class-description">
                    Class Description <span class="optional">(Optional)</span>
                </label>
                <textarea
                    id="class-description"
                    name="description"
                    maxlength="200"
                    rows="4"
                    placeholder="Add a short description..."
                ></textarea>
                <div class="character-count character-count-right">
                    <span id="description-count">0 / 200</span>
                </div>

                <div class="generated-code-box">
                    <div>
                        <strong>Generated Class Code</strong>
                        <p>
                            Share this code with your students so they can join
                            your class.
                        </p>
                    </div>

                    <div class="generated-code-actions">
                        <input
                            id="generated-class-code"
                            type="text"
                            readonly
                            aria-label="Generated class code"
                        >
                        <button
                            id="regenerate-code"
                            type="button"
                            title="Generate another code"
                            aria-label="Generate another class code"
                        >
                            ↻
                        </button>
                    </div>
                </div>

                <p class="code-note">
                    ⓘ Once you created the class, you can no longer change the
                    class code.
                </p>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="secondary-button"
                        id="cancel-class-modal"
                    >
                        Cancel
                    </button>
                    <button type="submit" class="create-class-button">
                        + Create Class
                    </button>
                </div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop profile-modal-backdrop" id="profile-modal" hidden>
        <section
            class="class-modal profile-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="profile-modal-title"
        >
            <button
                type="button"
                class="modal-close"
                id="close-profile-modal"
                aria-label="Close profile"
            >
                ×
            </button>

            <div class="profile-modal-heading">
                <div class="profile-modal-avatar" aria-hidden="true">
                    <img class="profile-avatar-image" id="profile-modal-image" alt="" hidden>
                    <span id="profile-modal-initial">T</span>
                </div>
                <div>
                    <h2 id="profile-modal-title">Your Profile</h2>
                    <p id="profile-modal-subtitle">Your account information</p>
                </div>
            </div>

            <div id="profile-display-view" class="profile-display-view">
                <div class="profile-detail">
                    <span>Full name</span>
                    <strong id="profile-detail-name">Teacher</strong>
                </div>
                <div class="profile-detail">
                    <span>Email address</span>
                    <strong id="profile-detail-email">Loading...</strong>
                </div>
                <div class="profile-modal-actions">
                    <button type="button" class="secondary-button" id="close-profile-view">Close</button>
                    <button type="button" class="create-class-button" id="start-profile-edit">Edit Profile</button>
                </div>
            </div>

            <form id="edit-profile-form" class="profile-edit-form" hidden>
                <div class="profile-image-editor">
                    <div class="profile-image-preview" aria-hidden="true">
                        <img class="profile-avatar-image" id="profile-edit-image-preview" alt="" hidden>
                        <span id="profile-edit-initial">T</span>
                    </div>
                    <div class="profile-image-controls">
                        <label class="profile-image-button" for="profile-image-input">Change photo</label>
                        <input
                            id="profile-image-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            aria-describedby="profile-image-note"
                        >
                        <span id="profile-image-note">JPG, PNG, or WebP · Max 5 MB</span>
                    </div>
                </div>

                <label for="profile-edit-name">Full name</label>
                <input
                    id="profile-edit-name"
                    name="name"
                    type="text"
                    maxlength="100"
                    autocomplete="name"
                    required
                >

                <label for="profile-edit-email">Email address</label>
                <input
                    id="profile-edit-email"
                    type="email"
                    autocomplete="email"
                    readonly
                    aria-describedby="profile-email-note"
                >
                <p class="profile-email-note" id="profile-email-note">Email address cannot be changed here.</p>

                <div class="profile-modal-actions">
                    <button type="button" class="secondary-button" id="cancel-profile-edit">Cancel</button>
                    <button type="submit" class="create-class-button" id="save-profile-button">Save changes</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
