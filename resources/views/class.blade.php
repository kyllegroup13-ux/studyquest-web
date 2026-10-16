<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyQuest - Class</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite([
        'resources/css/app.css',
        'resources/js/class.js'
    ])

    <style>
        :root {
            --sq-blue: #27b5ef;
            --sq-deep-blue: #2478f2;
            --sq-light-blue: #eaf7ff;
            --sq-pale-blue: #dff3ff;
            --sq-border: #d8dfe7;
            --sq-page: #f3f7fd;
            --sq-card: #ffffff;
            --sq-muted: #6d7783;
            --sq-text: #101828;
            --sq-shadow: 0 10px 24px rgba(19, 44, 79, 0.08);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-width: 320px;
            color: var(--sq-text);
            background: var(--sq-page);
            font-family: Arial, Helvetica, sans-serif;
        }

        button,
        input {
            font: inherit;
        }

        a {
            text-decoration: none;
        }

        .sq-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            height: 86px;
            background: #ffffff;
            border-bottom: 1px solid #edf1f5;
        }

        .sq-topbar-inner {
            width: min(1180px, calc(100% - 48px));
            height: 100%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sq-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 0;
        }

        .sq-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--sq-blue);
            margin-left: 0;
        }

        .sq-brand-mark {
            width: 44px;
            height: 44px;
            object-fit: contain;
            display: block;
        }

        .sq-brand span {
            color: #2eb5f1;
            font-size: 23px;
            font-weight: 800;
        }

        .sq-back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            margin-left: 18px;
            border: 1px solid #e2e7ed;
            border-radius: 10px;
            background: #ffffff;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sq-back-link:hover {
            background: #f6f9ff;
            border-color: #d3dbe5;
        }

        .sq-profile {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dff3ff;
            color: #1678b8;
            font-size: 21px;
            font-weight: 800;
            text-transform: uppercase;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(16, 24, 40, 0.04);
        }


        .sq-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 32px;
            padding: 16px 24px 36px 28px;
            align-items: start;
        }

        .sq-main { min-width: 0; }

        .sq-course-banner {
            min-height: 190px;
            margin-top: 20px;
            padding: 24px 28px;
            border-radius: 22px;
            background: linear-gradient(135deg, var(--sq-light-blue), var(--sq-pale-blue));
            border: 1px solid rgba(39, 181, 239, 0.16);
            display: flex;
            align-items: center;
            gap: 24px;
            box-shadow: var(--sq-shadow);
        }

        .sq-course-icon {
            flex: 0 0 138px;
            width: 138px;
            height: 138px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--sq-deep-blue), var(--sq-blue));
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 58px;
            font-weight: 800;
            letter-spacing: -2px;
        }

        .sq-course-details { min-width: 0; }

        #class-name {
            margin: 0;
            color: #111827;
            font-size: clamp(26px, 2.4vw, 36px);
            line-height: 1.2;
            font-weight: 800;
        }

        .sq-course-label {
            margin: 8px 0 10px;
            color: var(--sq-muted);
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
        }

        .sq-code-row {
            min-height: 52px;
            width: fit-content;
            max-width: 100%;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 18px;
            border-radius: 12px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(39, 181, 239, 0.18);
            font-size: 18px;
        }

        .sq-code-row strong {
            color: #0a0f1a;
            font-weight: 700;
        }

        #subject-code {
            margin: 0;
            padding: 7px 10px;
            min-width: 136px;
            border-radius: 8px;
            background: #fff;
            color: #111827;
            font-weight: 700;
            border: 1px solid rgba(17, 24, 39, 0.08);
        }

        .sq-copy-symbol {
            margin-left: -10px;
            color: #2d3748;
            font-size: 18px;
        }

        .sq-posts-heading {
            margin: 32px 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .sq-posts-heading h2 {
            margin: 0;
            color: #111827;
            font-size: 30px;
            font-weight: 800;
        }

        .sq-add-post {
            min-height: 58px;
            padding: 0 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(180deg, #2ec0f8, #27b5ef);
            box-shadow: 0 6px 0 rgba(39, 181, 239, 0.2);
            color: #041018;
            font: inherit;
            font-size: 18px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
        }

        .sq-add-post span {
            font-size: 30px;
            font-weight: 400;
            line-height: 1;
        }

        .sq-post-card {
            min-height: 340px;
            padding: 22px 28px 24px;
            border: 1px solid var(--sq-border);
            border-radius: 18px;
            background: var(--sq-card);
            box-shadow: var(--sq-shadow);
            position: relative;
        }

        .sq-post-author {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sq-avatar {
            width: 64px;
            height: 64px;
            flex: 0 0 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dff3ff;
            color: #1678b8;
            font-size: 25px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sq-author-name {
            margin: 0 0 4px;
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
        }

        .sq-date {
            margin: 0;
            color: var(--sq-muted);
            font-size: 11px;
        }

        .sq-post-menu {
            position: absolute;
            right: 22px;
            top: 18px;
            border: 0;
            background: transparent;
            color: #374151;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
        }

        .sq-post-title {
            margin: 16px 0 0;
            color: #111827;
            font-size: 28px;
            line-height: 1.25;
            font-weight: 800;
        }

        .sq-post-description {
            margin: 8px 0 0;
            color: #475467;
            font-size: 16px;
            line-height: 1.5;
        }

        .sq-post-attachment {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 26px;
            padding: 10px 14px;
            border-radius: 10px;
            background: #eef7ff;
            color: #1f2937;
            font-size: 14px;
            font-weight: 600;
        }

        .sq-post-media {
            height: 96px;
            margin-top: 64px;
            border-radius: 10px;
            background: linear-gradient(135deg, #ced7df, #9ea7b1);
        }

        .sq-post-empty {
            padding: 26px 18px;
            border: 1px dashed #bfd9f5;
            border-radius: 16px;
            background: rgba(255,255,255,0.55);
            color: var(--sq-muted);
            font-size: 15px;
            text-align: center;
        }

        .sq-post-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-y: auto;
            background: rgba(15, 23, 42, 0.16);
            z-index: 1000;
        }

        .sq-post-modal.is-open {
            display: flex;
        }

        .sq-post-modal-card {
            width: min(100%, 760px);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            overscroll-behavior: contain;
            background: #f3f7fb;
            border-radius: 18px;
            padding: 20px 28px 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        }

        .sq-post-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .sq-post-modal-brand {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .sq-post-modal-icon {
            width: 76px;
            height: 76px;
            border-radius: 14px;
            background: #d9f1ff;
            display: grid;
            place-items: center;
            color: #1b7ad8;
            font-size: 38px;
            border: 1px solid rgba(27, 122, 216, 0.2);
        }

        .sq-post-modal-brand h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1.2;
            color: #111827;
        }

        .sq-post-modal-brand p {
            margin: 8px 0 0;
            font-size: 17px;
            color: #4b5563;
        }

        .sq-post-modal-close {
            width: 36px;
            height: 36px;
            border: 0;
            background: transparent;
            color: #111827;
            font-size: 42px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        .sq-post-form {
            display: grid;
            gap: 14px;
        }

        .sq-field {
            display: grid;
            gap: 8px;
        }

        .sq-field label {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .sq-field input,
        .sq-field textarea {
            width: 100%;
            border: 1px solid #b9c5d1;
            border-radius: 10px;
            background: #fff;
            color: #111827;
            font: inherit;
            padding: 12px 14px;
        }

        .sq-field textarea {
            min-height: 120px;
            resize: vertical;
        }

        .sq-field input:focus,
        .sq-field textarea:focus {
            outline: 0;
            border-color: #27b5ef;
            box-shadow: 0 0 0 3px rgba(39, 181, 239, 0.1);
        }

        .sq-char-count {
            display: flex;
            justify-content: flex-end;
            color: #64748b;
            font-size: 13px;
            margin-top: -4px;
        }

        .sq-divider {
            width: 100%;
            height: 1px;
            background: rgba(148, 163, 184, 0.6);
            margin: 4px 0 2px;
        }

        .sq-upload-wrapper {
            margin-top: 4px;
        }

        .sq-upload-box {
            width: 100%;
            min-height: 128px;
            border: 2px dashed #35baf2;
            border-radius: 14px;
            background: rgba(162, 218, 255, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .sq-upload-box.has-image {
            min-height: 0;
            padding: 12px;
            flex-direction: column;
            gap: 8px;
        }

        .sq-upload-box:hover {
            border-color: #1b7ad8;
            background: rgba(162, 218, 255, 0.22);
        }

        .sq-upload-box input {
            display: none;
        }

        .sq-upload-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            color: #1b7ad8;
        }

        .sq-upload-box .sq-image-preview {
            display: block;
            width: 100%;
            max-height: 220px;
            border-radius: 8px;
            object-fit: contain;
        }

        .sq-upload-box .sq-image-preview[hidden],
        .sq-upload-box .sq-upload-image-replace[hidden],
        .sq-upload-box .sq-upload-content[hidden] {
            display: none;
        }

        .sq-upload-image-replace {
            color: #1b7ad8;
            font-size: 13px;
            font-weight: 600;
        }

        .sq-upload-icon {
            width: 68px;
            height: 68px;
            border-radius: 16px;
            background: #d7f1ff;
            display: grid;
            place-items: center;
            font-size: 32px;
            color: #1b7ad8;
        }

        .sq-upload-text {
            display: grid;
            gap: 2px;
            color: #1f2937;
        }

        .sq-upload-text strong {
            font-size: 18px;
            font-weight: 700;
        }

        .sq-upload-text span {
            color: #5b6472;
            font-size: 15px;
        }

        .sq-upload-file-label {
            margin-top: 8px;
            color: #0f172a;
            font-size: 13px;
            font-weight: 600;
            display: block;
            min-height: 18px;
        }

        .sq-post-actions {
            display: flex;
            justify-content: flex-end;
            gap: 14px;
            margin-top: 12px;
        }

        .sq-cancel-button,
        .sq-submit-post {
            min-height: 48px;
            border: 0;
            border-radius: 12px;
            font: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .sq-cancel-button {
            min-width: 120px;
            background: #d8dde5;
            color: #111827;
        }

        .sq-submit-post {
            min-width: 128px;
            background: linear-gradient(180deg, #2ec0f8, #27b5ef);
            color: #08131c;
            box-shadow: 0 6px 0 rgba(39, 181, 239, 0.2);
        }

        .sq-students-panel {
            height: calc(100vh - 140px);
            min-height: 620px;
            padding: 24px 18px 20px 20px;
            border: 1px solid var(--sq-border);
            border-radius: 20px;
            background: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: var(--sq-shadow);
        }

        .sq-students-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }

        .sq-students-title h2 {
            margin: 0;
            color: #111827;
            font-size: 30px;
            font-weight: 800;
        }

        .sq-student-count {
            min-width: 48px;
            height: 44px;
            padding: 0 10px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #e9edf3;
            color: #111827;
            font-size: 22px;
            font-weight: 800;
        }

        .sq-search {
            height: 48px;
            padding: 0 12px;
            border: 1px solid #d4d8dd;
            border-radius: 11px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            background: #f8fafc;
        }

        .sq-search-icon {
            width: 16px;
            height: 16px;
            border: 3px solid #7d8591;
            border-radius: 50%;
            position: relative;
            flex: 0 0 16px;
        }

        .sq-search-icon::after {
            content: "";
            width: 8px;
            height: 3px;
            background: #7d8591;
            position: absolute;
            right: -6px;
            bottom: -3px;
            transform: rotate(48deg);
            border-radius: 3px;
        }

        #student-search {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            font: inherit;
            font-size: 15px;
            background: transparent;
            color: #111827;
        }

        #students-list {
            min-height: 0;
            overflow-y: auto;
        }

        .sq-student-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 6px 4px;
        }

        .sq-student-avatar {
            width: 52px;
            height: 52px;
            flex: 0 0 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dff3ff;
            color: #1678b8;
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sq-student-name {
            margin: 0 0 2px;
            color: #111827;
            font-size: 15px;
            font-weight: 700;
        }

        .sq-student-date {
            margin: 0;
            color: var(--sq-muted);
            font-size: 11px;
        }

        #students-loading, #students-empty {
            color: var(--sq-muted);
            font-size: 14px;
        }

        [hidden] { display: none !important; }

        @media (max-width: 1050px) {
            .sq-layout {
                grid-template-columns: minmax(0, 1fr) 320px;
                gap: 22px;
                padding-left: 20px;
            }
        }

        @media (max-width: 760px) {
            .sq-topbar {
                height: 82px;
                padding: 0 18px;
            }

            .sq-header-actions {
                gap: 10px;
            }

            .sq-brand {
                font-size: 22px;
                gap: 8px;
            }

            .sq-brand-mark {
                width: 30px;
                height: 30px;
                font-size: 26px;
            }

            .sq-back-link {
                min-height: 36px;
                padding: 0 12px;
                font-size: 12px;
            }

            .sq-profile {
                width: 44px;
                height: 44px;
            }

            .sq-layout {
                display: flex;
                flex-direction: column;
                padding: 10px 16px 28px;
            }

            .sq-main, .sq-students-panel {
                width: 100%;
            }

            .sq-course-banner {
                margin-top: 10px;
                min-height: 140px;
                padding: 16px;
                gap: 15px;
                border-radius: 18px;
            }

            .sq-course-icon {
                flex-basis: 84px;
                width: 84px;
                height: 84px;
                font-size: 34px;
                border-radius: 12px;
            }

            #class-name { font-size: 23px; }
            .sq-course-label { font-size: 15px; }
            .sq-code-row { gap: 10px; font-size: 15px; min-height: 42px; padding: 6px 10px; }
            #subject-code { min-width: 0; padding: 5px 8px; }
            .sq-copy-symbol { margin-left: 0; }
            .sq-posts-heading { margin-top: 26px; }
            .sq-posts-heading h2, .sq-students-title h2 { font-size: 26px; }
            .sq-add-post { min-height: 48px; padding: 0 14px; font-size: 15px; gap: 8px; }
            .sq-post-card { min-height: 300px; padding: 17px; }
            .sq-avatar { width: 52px; height: 52px; flex-basis: 52px; }
            .sq-post-title { font-size: 25px; }
            .sq-post-media { margin-top: 52px; height: 80px; }
            .sq-students-panel { height: auto; min-height: 0; margin-top: 8px; }
            #students-list { max-height: 400px; }
        }
    </style>
</head>

<body>
    <header class="sq-topbar">
        <div class="sq-topbar-inner">
            <div class="sq-header-actions">
                <a class="sq-brand" href="/dashboard" aria-label="StudyQuest dashboard">
                    <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="StudyQuest logo" class="sq-brand-mark">
                    <span>StudyQuest</span>
                </a>
                <a class="sq-back-link" href="/dashboard" aria-label="Back to dashboard">← Dashboard</a>
            </div>
            <div class="sq-profile" id="profile-initial" aria-label="Profile" title="Your profile"></div>
        </div>
    </header>

    <main id="class-page" data-class-id="{{ $classId }}" class="sq-layout">
        <section class="sq-main">
            <section class="sq-course-banner" aria-label="Class information">
                <div class="sq-course-icon" id="course-initials">IM</div>
                <div class="sq-course-details">
                    <h1 id="class-name">Loading class...</h1>
                    <p class="sq-course-label">Course Code</p>
                    <div class="sq-code-row">
                        <strong>Class Code</strong>
                        <p id="subject-code"></p>
                        <span class="sq-copy-symbol" aria-hidden="true">▢</span>
                    </div>
                </div>
            </section>

            <div class="sq-posts-heading">
                <h2>Posts</h2>
                <button class="sq-add-post" id="add-post-button" type="button">
                    <span aria-hidden="true">+</span> Add Post
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
                <span class="sq-search-icon" aria-hidden="true"></span>
                <input id="student-search" type="search" placeholder="Search Students" autocomplete="off">
            </label>

            <p id="students-loading">Loading students...</p>
            <div id="students-list"></div>
            <div id="students-empty" hidden>No students have joined this class yet.</div>
        </aside>
    </main>

    <div class="sq-post-modal" id="post-modal" aria-hidden="true">
        <div class="sq-post-modal-card" role="dialog" aria-modal="true" aria-labelledby="post-modal-title">
            <div class="sq-post-modal-header">
                <div class="sq-post-modal-brand">
                    <div class="sq-post-modal-icon" aria-hidden="true">📄</div>
                    <div>
                        <h2 id="post-modal-title">Add Post</h2>
                        <p>Share a learning material with your class.</p>
                    </div>
                </div>
                <button class="sq-post-modal-close" type="button" id="close-post-modal" aria-label="Close add post dialog">×</button>
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

                <div class="sq-upload-wrapper">
                    <label class="sq-upload-box" for="post-file">
                        <input id="post-file" name="post_file" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp">
                        <div class="sq-upload-content" id="post-upload-content">
                            <div class="sq-upload-icon" aria-hidden="true">☁</div>
                            <div class="sq-upload-text">
                                <strong>Click to upload</strong>
                                <span>PDF, DOCX, PPTX, IMG (Max 10MB)</span>
                            </div>
                        </div>
                        <img class="sq-image-preview" id="post-image-preview" alt="Preview of selected image" hidden>
                        <span class="sq-upload-image-replace" id="post-upload-image-replace" hidden>Click to change image</span>
                    </label>
                    <span class="sq-upload-file-label" id="post-file-name">No file selected</span>
                </div>

                <div class="sq-post-actions">
                    <button class="sq-cancel-button" type="button" id="cancel-post-modal">Cancel</button>
                    <button class="sq-submit-post" type="submit">+ Add Post</button>
                </div>
            </form>
        </div>
    </div>


</body>
</html>
