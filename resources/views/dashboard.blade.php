<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyQuest - Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <div class="dashboard-header-inner">
            <a href="/dashboard" class="dashboard-brand">
                <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="StudyQuest logo">
                <span>StudyQuest</span>
            </a>

            <div class="profile-area">
                <button
                    class="profile-button"
                    id="profile-button"
                    type="button"
                    aria-label="Open account menu"
                    aria-expanded="false"
                >
                    <span id="profile-initial">T</span>
                </button>

                <div class="profile-menu" id="profile-menu" hidden>
                    <div class="profile-menu-info">
                        <strong id="profile-name">Teacher</strong>
                        <span id="profile-email">Loading...</span>
                    </div>

                    <button type="button" id="logout-button" class="logout-button">
                        Log out
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="dashboard-main">
        <div class="dashboard-title-row">
            <h1>Classes</h1>

            <button type="button" class="add-class-button" id="open-class-modal">
                <span>+</span>
                Add class
            </button>
        </div>

        <div class="classes-grid" id="classes-grid">
            <div class="classes-loading" id="classes-loading">
                Loading your classes...
            </div>
        </div>

        <div class="empty-classes" id="empty-classes" hidden>
            <div class="empty-classes-icon">+</div>
            <h2>No classes yet</h2>
            <p>Create your first class to get started.</p>
            <button type="button" class="add-class-button" id="empty-add-class">
                + Add class
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
                ×
            </button>

            <div class="modal-heading">
                <div class="modal-icon" aria-hidden="true">
                    <span>👥</span>
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
</body>
</html>
