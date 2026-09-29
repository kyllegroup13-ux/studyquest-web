<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>StudyQuest - Class</title>

    @vite([
    'resources/css/app.css',
    'resources/js/class.js'
    ])
</head>

<body>

    <main
        id="class-page"
        data-class-id="{{ $classId }}">

        <a href="/dashboard">
            ← Back to Dashboard
        </a>

        <h1 id="class-name">
            Loading class...
        </h1>

        <p id="subject-code"></p>

        <hr>

        <h2>Students</h2>

        <p id="students-loading">
            Loading students...
        </p>

        <div id="students-list"></div>

        <div
            id="students-empty"
            hidden>
            No students have joined this class yet.
        </div>

    </main>

</body>

</html>