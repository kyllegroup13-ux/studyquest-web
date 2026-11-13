import { onAuthStateChanged } from 'firebase/auth';
import { auth, db } from './firebase';
import { showAlert, showConfirmation } from './alert-modal';
import {
    collection,
    onSnapshot,
    query,
} from 'firebase/firestore';

let generatedActivityPreview = null;
let generatingActivityId = null;
let previewQuestionIndex = 0;
let previewPreviousFocus = null;
let savingActivityQuestions = false;
let viewingSavedQuestions = false;
let publishingActivityId = null;
let selectedPublishActivity = null;
let publishPreviousFocus = null;
let publishSubmitting = false;

export function initializeGameActivityTabs() {
    const postsTab = document.getElementById('posts-tab');
    const activitiesTab = document.getElementById('activities-tab');

    const postsPanel = document.getElementById('posts-panel');
    const activitiesPanel = document.getElementById('activities-panel');

    if (!postsTab || !activitiesTab || !postsPanel || !activitiesPanel) {
        return;
    }

    const tabs = [postsTab, activitiesTab];

    function activateTab(activeTab) {
        const showingPosts = activeTab === postsTab;

        postsPanel.hidden = !showingPosts;
        activitiesPanel.hidden = showingPosts;

        tabs.forEach((tab) => {
            const selected = tab === activeTab;

            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
    }

    postsTab.addEventListener('click', () => {
        activateTab(postsTab);
    });

    activitiesTab.addEventListener('click', () => {
        activateTab(activitiesTab);
    });

    tabs.forEach((tab, index) => {
        tab.addEventListener('keydown', (event) => {
            let nextIndex;

            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activateTab(tabs[nextIndex]);
            tabs[nextIndex].focus();
        });
    });

    activateTab(postsTab);
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializeGameActivityTabs,
        { once: true }
    );
} else {
    initializeGameActivityTabs();
}


const previewGameLabels = {
    mind_rush: 'Mind Rush',
    link_up: 'Link Up',
    word_forge: 'Word Forge',
    letter_quest: 'Letter Quest',
};

function previewElement(tag, text, className = '') {
    const element = document.createElement(tag);

    element.textContent = String(text ?? '');

    if (className) {
        element.className = className;
    }

    return element;
}

function renderPreviewQuestion() {
    const preview = generatedActivityPreview;

    if (!preview) return;

    const modal = document.getElementById(
        'sq-question-preview-modal'
    );

    const content = document.getElementById(
        'sq-question-preview-content'
    );

    const counter = document.getElementById(
        'sq-preview-counter'
    );

    const previous = document.getElementById(
        'sq-preview-previous'
    );

    const next = document.getElementById(
        'sq-preview-next'
    );

    if (!modal || !content || !counter || !previous || !next) {
        return;
    }

    const items = preview.questions.items;
    const item = items[previewQuestionIndex];

    if (!item) return;

    content.replaceChildren();

    const heading = previewElement(
        'h3',
        `Question ${previewQuestionIndex + 1}`,
        'sq-preview-question-heading'
    );

    content.appendChild(heading);

    switch (preview.gameType) {
        case 'mind_rush': {
            content.appendChild(
                previewElement(
                    'p',
                    item.question,
                    'sq-preview-question-text'
                )
            );

            const choices = document.createElement('div');
            choices.className = 'sq-preview-choices';

            item.choices.forEach((choice, index) => {
                const option = previewElement(
                    'div',
                    `${String.fromCharCode(65 + index)}. ${choice}`,
                    'sq-preview-choice'
                );

                if (index === item.correctIndex) {
                    option.classList.add('is-correct');
                    option.appendChild(
                        previewElement(
                            'span',
                            'Correct answer',
                            'sq-preview-correct-label'
                        )
                    );
                }

                choices.appendChild(option);
            });

            content.appendChild(choices);
            break;
        }

        case 'link_up': {
            content.appendChild(
                previewElement(
                    'p',
                    'Matching Term',
                    'sq-preview-field-label'
                )
            );

            content.appendChild(
                previewElement(
                    'div',
                    item.term,
                    'sq-preview-answer-box'
                )
            );

            content.appendChild(
                previewElement(
                    'p',
                    'Correct Definition',
                    'sq-preview-field-label'
                )
            );

            content.appendChild(
                previewElement(
                    'div',
                    item.definition,
                    'sq-preview-answer-box is-correct'
                )
            );
            break;
        }

        case 'word_forge': {
            content.appendChild(
                previewElement(
                    'p',
                    'Complete the Statement',
                    'sq-preview-field-label'
                )
            );

            content.appendChild(
                previewElement(
                    'div',
                    item.statement,
                    'sq-preview-question-text'
                )
            );

            content.appendChild(
                previewElement(
                    'p',
                    'Correct Answers',
                    'sq-preview-field-label'
                )
            );

            const answers = document.createElement('div');
            answers.className = 'sq-preview-chip-list';

            item.answers.forEach((answer) => {
                answers.appendChild(
                    previewElement(
                        'span',
                        answer,
                        'sq-preview-chip is-correct'
                    )
                );
            });

            content.appendChild(answers);

            content.appendChild(
                previewElement(
                    'p',
                    'Available Choices',
                    'sq-preview-field-label'
                )
            );

            const choices = document.createElement('div');
            choices.className = 'sq-preview-chip-list';

            item.choices.forEach((choice) => {
                choices.appendChild(
                    previewElement(
                        'span',
                        choice,
                        'sq-preview-chip'
                    )
                );
            });

            content.appendChild(choices);
            break;
        }

        case 'letter_quest': {
            content.appendChild(
                previewElement(
                    'p',
                    item.question,
                    'sq-preview-question-text'
                )
            );

            content.appendChild(
                previewElement(
                    'p',
                    'Correct Answer',
                    'sq-preview-field-label'
                )
            );

            content.appendChild(
                previewElement(
                    'div',
                    item.answer,
                    'sq-preview-answer-box is-correct'
                )
            );
            break;
        }

        default:
            content.appendChild(
                previewElement(
                    'p',
                    'Unsupported game type.'
                )
            );
    }

    counter.textContent =
        `${previewQuestionIndex + 1} / ${items.length}`;

    previous.disabled = previewQuestionIndex === 0;
    next.disabled = previewQuestionIndex === items.length - 1;

    content.scrollTop = 0;
}


function openQuestionPreview() {
    if (!generatedActivityPreview) return;

    const modal = document.getElementById(
        'sq-question-preview-modal'
    );

    const dialog = modal?.querySelector(
        '.sq-question-preview-dialog'
    );

    const subtitle = document.getElementById(
        'sq-question-preview-subtitle'
    );

    if (!modal || !dialog || !subtitle) return;

    previewPreviousFocus = document.activeElement;
    previewQuestionIndex = 0;

    const gameLabel =
        previewGameLabels[generatedActivityPreview.gameType]
        ?? 'Game Activity';

    subtitle.textContent =
        `${gameLabel} · ` +
        `${generatedActivityPreview.questions.items.length} questions`;

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');

    const saveButton = document.getElementById(
        'sq-preview-save'
    );

    if (saveButton) {
        saveButton.disabled =
            viewingSavedQuestions || savingActivityQuestions;

        saveButton.textContent = viewingSavedQuestions
            ? 'Questions Already Saved'
            : 'Save Questions';
    }

    renderPreviewQuestion();

    dialog.focus();
}

function closeQuestionPreview() {
    if (savingActivityQuestions) return;

    const modal = document.getElementById(
        'sq-question-preview-modal'
    );

    if (!modal || modal.hidden) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');

    if (previewPreviousFocus?.isConnected) {
        previewPreviousFocus.focus();
    }

    previewPreviousFocus = null;
}


async function saveActivityQuestions() {
    if (
        savingActivityQuestions ||
        viewingSavedQuestions ||
        !generatedActivityPreview
    ) {
        return;
    }

    const saveButton = document.getElementById(
        'sq-preview-save'
    );

    const modal = document.getElementById(
        'sq-question-preview-modal'
    );

    if (!saveButton || !modal || modal.hidden) return;

    const classId = window.location.pathname
        .match(/^\/class\/([A-Za-z0-9_-]+)\/?$/)?.[1];

    if (!classId) {
        showAlert('Class ID is missing.');
        return;
    }

    const user = auth.currentUser;

    if (!user) {
        showAlert('Please sign in again.');
        return;
    }

    const preview = generatedActivityPreview;

    const originalText = saveButton.textContent;

    savingActivityQuestions = true;
    saveButton.disabled = true;
    saveButton.textContent = 'Saving Questions...';

    try {
        const idToken = await user.getIdToken();

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error('CSRF token is missing.');
        }

        const response = await fetch(
            '/class/activities/save-questions',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Authorization': `Bearer ${idToken}`,
                },
                body: JSON.stringify({
                    classId,
                    activityId: preview.activityId,
                    questions: preview.questions,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to save questions.'
            );
        }

        if (!data.success) {
            throw new Error(
                'The server did not confirm the save.'
            );
        }

        saveButton.textContent = 'Questions Saved';
        saveButton.disabled = true;

        showAlert(
            'Successfully saved 10 questions to Firestore. ' +
            'The activity remains a draft.'
        );

        generatedActivityPreview = null;

        savingActivityQuestions = false;
        closeQuestionPreview();
    } catch (error) {
        showAlert(
            error.message || 'Could not save questions.'
        );

        saveButton.disabled = false;
        saveButton.textContent = originalText;
    } finally {
        savingActivityQuestions = false;
    }
}

function initializeQuestionPreview() {
    const modal = document.getElementById(
        'sq-question-preview-modal'
    );

    if (!modal || modal.dataset.initialized === 'true') {
        return;
    }

    modal.dataset.initialized = 'true';

    const previous = document.getElementById(
        'sq-preview-previous'
    );

    const next = document.getElementById(
        'sq-preview-next'
    );

    const saveButton = document.getElementById(
        'sq-preview-save'
    );

    saveButton?.addEventListener(
        'click',
        saveActivityQuestions
    );

    modal.querySelectorAll('[data-preview-close]')
        .forEach((element) => {
            element.addEventListener(
                'click',
                closeQuestionPreview
            );
        });

    previous?.addEventListener('click', () => {
        if (previewQuestionIndex > 0) {
            previewQuestionIndex--;
            renderPreviewQuestion();
        }
    });

    next?.addEventListener('click', () => {
        const total =
            generatedActivityPreview?.questions.items.length ?? 0;

        if (previewQuestionIndex < total - 1) {
            previewQuestionIndex++;
            renderPreviewQuestion();
        }
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeQuestionPreview();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(
            modal.querySelectorAll(
                'button:not([disabled])'
            )
        ).filter((element) => element.getClientRects().length > 0);

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (
            event.shiftKey &&
            (document.activeElement === first ||
                document.activeElement === modal.querySelector(
                    '.sq-question-preview-dialog'
                ))
        ) {
            event.preventDefault();
            last.focus();
        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {
            event.preventDefault();
            first.focus();
        }
    });
}


async function generateActivityQuestions(
    classId,
    activityId,
    button,
    messageElement
) {
    if (generatingActivityId !== null) return;

    const user = auth.currentUser;

    if (!user) {
        messageElement.textContent =
            'Please sign in again to generate questions.';
        messageElement.hidden = false;
        return;
    }

    generatingActivityId = activityId;
    button.disabled = true;
    button.textContent = 'Generating Questions...';

    messageElement.hidden = true;
    messageElement.textContent = '';

    try {
        const idToken = await user.getIdToken();

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error('CSRF token is missing.');
        }

        const response = await fetch(
            '/class/activities/generate',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Authorization': `Bearer ${idToken}`,
                },
                body: JSON.stringify({
                    classId,
                    activityId,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Question generation failed.'
            );
        }

        if (
            !data.questions ||
            !Array.isArray(data.questions.items) ||
            data.questions.items.length !== 10
        ) {
            throw new Error(
                'The server returned an invalid question set.'
            );
        }

        viewingSavedQuestions = false;

        generatedActivityPreview = {
            activityId,
            gameType: data.gameType,
            questions: data.questions,
        };

        openQuestionPreview();

        messageElement.textContent =
            'Successfully generated 10 questions. ' +
            'Question preview will be added next.';
        messageElement.dataset.state = 'success';
        messageElement.hidden = false;

        console.log(
            'StudyQuest activity questions generated:',
            {
                activityId,
                gameType: data.gameType,
                count: data.questions.items.length,
            }
        );
    } catch (error) {
        messageElement.textContent =
            error.message || 'Could not generate questions.';
        messageElement.dataset.state = 'error';
        messageElement.hidden = false;
    } finally {
        generatingActivityId = null;
        button.disabled = false;
        button.textContent = 'Generate Questions';
    }
}


function viewSavedActivityQuestions(activity) {
    const questions = activity.questions;

    if (
        !questions ||
        !Array.isArray(questions.items) ||
        questions.items.length !== 10
    ) {
        showAlert(
            'Saved questions are missing or incomplete.'
        );
        return;
    }

    if (![
        'mind_rush',
        'link_up',
        'word_forge',
        'letter_quest',
    ].includes(activity.gameType)) {
        showAlert('Unsupported game type.');
        return;
    }

    viewingSavedQuestions = true;

    generatedActivityPreview = {
        activityId: activity.id,
        gameType: activity.gameType,
        questions,
    };

    openQuestionPreview();
}


function openPublishModal(activity, triggerButton) {
    if (
        publishingActivityId !== null ||
        publishSubmitting ||
        activity.status !== 'draft' ||
        Number(activity.questionCount) !== 10 ||
        !Array.isArray(activity.questions?.items) ||
        activity.questions.items.length !== 10
    ) {
        return;
    }

    const modal = document.getElementById('sq-publish-modal');
    if (!modal) return;


    selectedPublishActivity = {
        id: activity.id,
        title: activity.title,
        gameType: activity.gameType,
        maxAttempts: activity.maxAttempts,
        deadlineAt: activity.deadlineAt ?? null,
    };


    publishPreviousFocus = triggerButton;

    document.getElementById(
        'sq-publish-activity-title'
    ).textContent = activity.title || 'Untitled Activity';

    document.getElementById(
        'sq-publish-game'
    ).textContent =
        previewGameLabels[activity.gameType] ?? 'Game';

    document.getElementById(
        'sq-publish-attempts'
    ).textContent = String(activity.maxAttempts ?? 1);


    const publishDeadline = document.getElementById(
        'sq-publish-deadline'
    );

    if (publishDeadline) {
        publishDeadline.textContent = activity.deadlineAt?.toDate
            ? activity.deadlineAt.toDate().toLocaleString('en-PH', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            }) + ' PHT'
            : 'No deadline';
    }

    const error = document.getElementById('sq-publish-error');
    error.hidden = true;
    error.textContent = '';

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');

    modal.querySelector('.sq-publish-dialog')?.focus();
}

function closePublishModal() {
    if (publishSubmitting) return;

    const modal = document.getElementById('sq-publish-modal');
    if (!modal || modal.hidden) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');

    selectedPublishActivity = null;

    if (publishPreviousFocus?.isConnected) {
        publishPreviousFocus.focus();
    }

    publishPreviousFocus = null;
}

async function confirmPublishActivity() {
    if (
        publishSubmitting ||
        !selectedPublishActivity
    ) {
        return;
    }

    const classId = document.getElementById(
        'class-page'
    )?.dataset.classId;

    const user = auth.currentUser;

    const button = document.getElementById(
        'sq-publish-confirm'
    );

    const errorElement = document.getElementById(
        'sq-publish-error'
    );

    if (!button || !errorElement) return;

    publishSubmitting = true;
    publishingActivityId = selectedPublishActivity.id;

    button.disabled = true;
    button.textContent = 'Publishing...';

    errorElement.hidden = true;
    errorElement.textContent = '';

    try {
        if (!classId) {
            throw new Error('Class ID is missing.');
        }

        if (!user) {
            throw new Error('Please sign in again.');
        }

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error('CSRF token is missing.');
        }

        const idToken = await user.getIdToken();

        const response = await fetch(
            '/class/activities/publish',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Authorization': `Bearer ${idToken}`,
                },
                body: JSON.stringify({
                    classId,
                    activityId: selectedPublishActivity.id,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || 'Could not publish activity.'
            );
        }

        publishSubmitting = false;
        closePublishModal();

        showAlert('Activity published successfully.');
    } catch (error) {
        errorElement.textContent =
            error.message || 'Publishing failed.';

        errorElement.hidden = false;
    } finally {
        publishSubmitting = false;
        publishingActivityId = null;

        button.disabled = false;
        button.textContent = 'Publish Activity';
    }
}

function initializePublishModal() {
    const modal = document.getElementById('sq-publish-modal');

    if (!modal || modal.dataset.initialized === 'true') {
        return;
    }

    modal.dataset.initialized = 'true';

    modal.querySelectorAll('[data-publish-close]')
        .forEach((element) => {
            element.addEventListener(
                'click',
                closePublishModal
            );
        });

    document.getElementById('sq-publish-confirm')
        ?.addEventListener('click', confirmPublishActivity);

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePublishModal();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(
            modal.querySelectorAll('button:not([disabled])')
        ).filter(
            (element) => element.getClientRects().length > 0
        );

        if (!focusable.length) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (
            event.shiftKey &&
            (document.activeElement === first ||
                document.activeElement ===
                modal.querySelector('.sq-publish-dialog'))
        ) {
            event.preventDefault();
            last.focus();
        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {
            event.preventDefault();
            first.focus();
        }
    });
}


let selectedScoreActivity = null;
let scorePreviousFocus = null;
let scoreRequestVersion = 0;

function scoreNode(tag, value, className = '') {
    const node = document.createElement(tag);
    node.textContent = String(value ?? '');
    if (className) node.className = className;
    return node;
}

function switchScoreTab(tab) {
    const overview = tab === 'overview';

    document.getElementById('sq-scores-overview').hidden = !overview;
    document.getElementById('sq-scores-history').hidden = overview;

    for (const [name, selected] of [
        ['overview', overview],
        ['history', !overview],
    ]) {
        const button = document.getElementById(`sq-scores-${name}-tab`);
        button.setAttribute('aria-selected', String(selected));
        button.tabIndex = selected ? 0 : -1;
    }
}

function renderActivityScores(data) {
    const summary = document.getElementById('sq-scores-summary');
    const students = document.getElementById('sq-scores-students');
    const history = document.getElementById('sq-scores-history');

    summary.replaceChildren();
    students.replaceChildren();
    history.replaceChildren();

    const metrics = document.createElement('div');
    metrics.className = 'sq-scores-metrics';

    for (const [label, value] of [
        ['Total Students', data.totalStudents],
        ['Completed', data.completedStudents],
        ['Average Best Score',
            data.averageHighestScore === null
                ? '—'
                : `${data.averageHighestScore}/${data.maxScore}`],
    ]) {
        const metric = scoreNode('div', '', 'sq-scores-metric');
        metric.append(
            scoreNode('strong', value),
            scoreNode('span', label)
        );
        metrics.appendChild(metric);
    }

    summary.appendChild(metrics);

    const studentTable = document.createElement('table');
    studentTable.className = 'sq-scores-table';
    const studentHead = document.createElement('thead');
    const studentHeadRow = document.createElement('tr');

    for (const heading of ['Student', 'Best Score', 'Attempts', 'Status']) {
        studentHeadRow.appendChild(scoreNode('th', heading));
    }

    studentHead.appendChild(studentHeadRow);
    studentTable.appendChild(studentHead);

    const studentBody = document.createElement('tbody');

    for (const student of data.students) {
        const row = document.createElement('tr');
        const statusLabels = {
            completed: 'Completed',
            in_progress: 'In Progress',
            not_started: 'Not Started',
        };

        for (const value of [
            student.name,
            student.highestScore === null
                ? '—'
                : `${student.highestScore}/${data.maxScore}`,
            `${student.attemptsUsed}/${student.maxAttempts}`,
            statusLabels[student.status] ?? student.status,
        ]) {
            row.appendChild(scoreNode('td', value));
        }

        studentBody.appendChild(row);
    }

    studentTable.appendChild(studentBody);

    if (data.students.length === 0) {
        students.appendChild(scoreNode('p', 'No enrolled students found.'));
    } else {
        const wrapper = document.createElement('div');
        wrapper.className = 'sq-scores-table-wrap';
        wrapper.appendChild(studentTable);
        students.appendChild(wrapper);
    }

    const historyTable = document.createElement('table');
    historyTable.className = 'sq-scores-table';
    const historyHead = document.createElement('thead');
    const historyHeadRow = document.createElement('tr');

    for (const heading of [
        'Student', 'Attempt', 'Score', 'Status', 'Finished',
    ]) {
        historyHeadRow.appendChild(scoreNode('th', heading));
    }

    historyHead.appendChild(historyHeadRow);
    historyTable.appendChild(historyHead);

    const historyBody = document.createElement('tbody');
    let historyCount = 0;

    for (const student of data.students) {
        for (const attempt of student.history) {
            historyCount++;
            const row = document.createElement('tr');

            const finished = attempt.finishedAt
                ? new Date(attempt.finishedAt).toLocaleString()
                : '—';

            for (const value of [
                student.name,
                `#${attempt.attemptNumber}`,
                attempt.score === null
                    ? '—'
                    : `${attempt.score}/${data.maxScore}`,
                attempt.status,
                finished,
            ]) {
                row.appendChild(scoreNode('td', value));
            }

            historyBody.appendChild(row);
        }
    }

    historyTable.appendChild(historyBody);

    if (historyCount === 0) {
        history.appendChild(scoreNode('p', 'No attempts recorded yet.'));
    } else {
        const wrapper = document.createElement('div');
        wrapper.className = 'sq-scores-table-wrap';
        wrapper.appendChild(historyTable);
        history.appendChild(wrapper);
    }
}

async function loadActivityScores() {
    if (!selectedScoreActivity) return;

    const requestVersion = ++scoreRequestVersion;
    const { activityId, classId } = selectedScoreActivity;
    const message = document.getElementById('sq-scores-message');
    const refresh = document.getElementById('sq-scores-refresh');

    message.hidden = false;
    message.textContent = 'Loading student scores...';
    refresh.disabled = true;

    try {
        const user = auth.currentUser;
        if (!user) throw new Error('Please sign in again.');

        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) throw new Error('CSRF token is missing.');

        const idToken = await user.getIdToken();

        const response = await fetch('/class/activities/scores', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Authorization': `Bearer ${idToken}`,
            },
            body: JSON.stringify({ classId, activityId }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not load scores.');
        }

        if (requestVersion !== scoreRequestVersion) return;

        renderActivityScores(data);
        message.hidden = true;
    } catch (error) {
        if (requestVersion !== scoreRequestVersion) return;
        message.textContent = error.message || 'Could not load scores.';
    } finally {
        if (requestVersion === scoreRequestVersion) {
            refresh.disabled = false;
        }
    }
}

function openActivityScores(activity, classId, triggerButton) {
    const modal = document.getElementById('sq-scores-modal');
    if (!modal) return;

    scorePreviousFocus = triggerButton;
    selectedScoreActivity = {
        activityId: activity.id,
        classId,
    };

    document.getElementById('sq-scores-subtitle').textContent =
        `${activity.title} · ${previewGameLabels[activity.gameType] ?? 'Game'}`;

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');

    document.getElementById('sq-scores-summary').replaceChildren();
    document.getElementById('sq-scores-students').replaceChildren();
    document.getElementById('sq-scores-history').replaceChildren();

    switchScoreTab('overview');
    modal.querySelector('.sq-question-preview-dialog')?.focus();
    loadActivityScores();
}

function closeActivityScores() {
    const modal = document.getElementById('sq-scores-modal');
    if (!modal || modal.hidden) return;

    scoreRequestVersion++;
    selectedScoreActivity = null;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');

    if (scorePreviousFocus?.isConnected) {
        scorePreviousFocus.focus();
    }
    scorePreviousFocus = null;
}

function initializeScoreMonitoring() {
    const modal = document.getElementById('sq-scores-modal');
    if (!modal || modal.dataset.initialized === 'true') return;

    modal.dataset.initialized = 'true';

    modal.querySelectorAll('[data-scores-close]').forEach(button => {
        button.addEventListener('click', closeActivityScores);
    });

    document.getElementById('sq-scores-refresh')
        ?.addEventListener('click', loadActivityScores);

    document.getElementById('sq-scores-overview-tab')
        ?.addEventListener('click', () => switchScoreTab('overview'));

    document.getElementById('sq-scores-history-tab')
        ?.addEventListener('click', () => switchScoreTab('history'));

    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeActivityScores();
        }
    });
}


let deletingActivityId = null;

async function deleteDraftActivity(activity, classId, button) {
    if (deletingActivityId !== null || activity.status !== 'draft') {
        return;
    }

    const confirmed = await showConfirmation(
        `Delete "${activity.title || 'Untitled Activity'}"?\n\n` +
        'This will permanently delete the draft and its saved questions.\n\n' +
        'This action cannot be undone.',
        {
            title: 'Delete draft activity?',
            confirmLabel: 'Delete activity',
            cancelLabel: 'Cancel',
        }
    );

    if (!confirmed) return;

    const user = auth.currentUser;

    if (!user) {
        showAlert('Please sign in again.');
        return;
    }

    deletingActivityId = activity.id;
    button.disabled = true;
    button.textContent = 'Deleting...';

    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error('CSRF token is missing.');
        }

        const idToken = await user.getIdToken();

        const response = await fetch(
            `/class/activities/${encodeURIComponent(activity.id)}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Authorization': `Bearer ${idToken}`,
                },
                body: JSON.stringify({ classId }),
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || 'Could not delete draft activity.'
            );
        }

        showAlert('Draft activity deleted successfully.');
        // The existing Firestore onSnapshot listener
        // automatically refreshes the activity cards.
    } catch (error) {
        showAlert(
            error.message || 'Failed to delete draft activity.'
        );
    } finally {
        deletingActivityId = null;

        if (button.isConnected) {
            button.disabled = false;
            button.textContent = 'Delete';
        }
    }
}


let recoveringSourceActivityId = null;

async function uploadMissingActivitySource(activity, classId, button) {
    if (
        recoveringSourceActivityId !== null ||
        activity.status !== 'draft' ||
        activity.generationMethod !== 'file' ||
        activity.sourceFile?.publicId
    ) {
        return;
    }

    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = '.pdf,.docx,.txt';

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];

        if (!file) return;

        const extension = file.name.split('.').pop()?.toLowerCase();

        if (
            !['pdf', 'docx', 'txt'].includes(extension) ||
            file.size === 0 ||
            file.size > 10 * 1024 * 1024
        ) {
            showAlert(
                'Select a valid PDF, DOCX, or TXT document up to 10 MB.'
            );
            return;
        }

        const user = auth.currentUser;

        if (!user) {
            showAlert('Please sign in again.');
            return;
        }

        recoveringSourceActivityId = activity.id;
        button.disabled = true;
        button.textContent = 'Uploading Document...';

        try {
            const csrfToken = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!csrfToken) {
                throw new Error('CSRF token is missing.');
            }

            const idToken = await user.getIdToken();

            const formData = new FormData();
            formData.append('classId', classId);
            formData.append('activityId', activity.id);
            formData.append('file', file);

            const response = await fetch(
                '/class/activities/upload-source',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Authorization': `Bearer ${idToken}`,
                    },
                    body: formData,
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Document upload failed.'
                );
            }

            showAlert(
                'Source document uploaded successfully. ' +
                'You can now generate questions.'
            );
        } catch (error) {
            showAlert(
                error.message || 'Could not upload source document.'
            );
        } finally {
            recoveringSourceActivityId = null;

            if (button.isConnected) {
                button.disabled = false;
                button.textContent = 'Upload Source Document';
            }
        }
    }, { once: true });

    fileInput.click();
}


function renderGameActivities(container, activities, classId) {
    container.replaceChildren();

    if (activities.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'sq-post-empty';
        empty.textContent =
            'No game activities yet. Create your first activity.';
        container.appendChild(empty);
        return;
    }

    const gameNames = {
        mind_rush: 'Mind Rush',
        link_up: 'Link Up',
        word_forge: 'Word Forge',
        letter_quest: 'Letter Quest',
    };

    const gameIcons = {
        mind_rush: 'fa-bolt',
        link_up: 'fa-link',
        word_forge: 'fa-puzzle-piece',
        letter_quest: 'fa-font',
    };

    for (const activity of activities) {
        const card = document.createElement('article');
        card.className = 'sq-activity-card';

        const header = document.createElement('div');
        header.className = 'sq-activity-card-header';

        const identity = document.createElement('div');
        identity.className = 'sq-activity-identity';

        const icon = document.createElement('span');
        icon.className = 'sq-activity-icon';

        const iconElement = document.createElement('i');
        iconElement.className =
            `fa-solid ${gameIcons[activity.gameType] ?? 'fa-gamepad'}`;
        iconElement.setAttribute('aria-hidden', 'true');
        icon.appendChild(iconElement);

        const details = document.createElement('div');
        details.className = 'sq-activity-details';

        const title = document.createElement('h3');
        title.textContent = activity.title || 'Untitled Activity';

        const subtitle = document.createElement('p');
        const gameName =
            gameNames[activity.gameType] ?? 'Game';
        const source =
            activity.generationMethod === 'file'
                ? 'File-based'
                : 'Topic-based';

        subtitle.textContent = `${gameName} · ${source}`;

        details.append(title, subtitle);
        identity.append(icon, details);

        const status = document.createElement('span');
        status.className = 'sq-activity-status';

        // Determine whether the activity is still a draft.
        const isDraft = activity.status === 'draft';

        const isExpired =
            activity.status === 'published' &&
            activity.deadlineAt?.toDate &&
            Date.now() >= activity.deadlineAt.toDate().getTime();

        const hasSavedQuestions =
            Number(activity.questionCount ?? 0) === 10;

        const needsSourceFile =
            isDraft &&
            activity.generationMethod === 'file' &&
            !activity.sourceFile?.publicId;

        status.classList.add(
            isDraft ? 'sq-activity-status-draft'
                : 'sq-activity-status-published'
        );

        status.textContent = isDraft
            ? 'Draft'
            : isExpired
                ? 'Closed'
                : activity.status === 'published'
                    ? 'Published'
                    : activity.status || 'Unknown';

        if (isExpired) {
            status.classList.remove('sq-activity-status-published');
            status.classList.add('sq-activity-status-closed');
        }

        header.append(identity, status);
        card.appendChild(header);

        if (activity.description) {
            const description = document.createElement('p');
            description.className = 'sq-activity-description';
            description.textContent = activity.description;
            card.appendChild(description);
        }

        const footer = document.createElement('div');
        footer.className = 'sq-activity-card-footer';

        const attempts = document.createElement('span');
        attempts.textContent =
            `Maximum attempts: ${activity.maxAttempts ?? 1}`;

        const date = document.createElement('span');

        if (activity.createdAt?.toDate) {
            date.textContent =
                activity.createdAt.toDate().toLocaleDateString();
        }


        footer.append(attempts, date);

        if (activity.deadlineAt?.toDate) {
            const deadlineDate = activity.deadlineAt.toDate();
            const deadline = document.createElement('span');

            deadline.textContent = `Deadline: ${deadlineDate.toLocaleString(
                'en-PH',
                {
                    timeZone: 'Asia/Manila',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                }
            )} PHT`;

            footer.appendChild(deadline);
        }

        card.appendChild(footer);

        const actions = document.createElement('div');
        actions.className = 'sq-activity-actions';

        if (isDraft) {
            const questionAction = document.createElement('button');

            questionAction.type = 'button';
            questionAction.className = 'sq-activity-action';

            const isGenerating =
                generatingActivityId === activity.id;

            questionAction.disabled =
                generatingActivityId !== null ||
                publishingActivityId !== null ||
                needsSourceFile;

            questionAction.textContent = hasSavedQuestions
                ? 'View Saved Questions'
                : isGenerating
                    ? 'Generating Questions...'
                    : needsSourceFile
                        ? 'Source Document Required'
                        : 'Generate Questions';

            const message = document.createElement('p');
            message.className = 'sq-activity-generation-message';
            message.hidden = true;
            message.setAttribute('role', 'status');
            message.setAttribute('aria-live', 'polite');

            questionAction.addEventListener('click', () => {
                if (hasSavedQuestions) {
                    viewSavedActivityQuestions(activity);
                    return;
                }

                generateActivityQuestions(
                    classId,
                    activity.id,
                    questionAction,
                    message
                );
            });

            actions.appendChild(questionAction);


            if (needsSourceFile) {
                const uploadButton = document.createElement('button');

                uploadButton.type = 'button';
                uploadButton.className = 'sq-activity-action';
                uploadButton.textContent = 'Upload Source Document';

                uploadButton.addEventListener('click', () => {
                    uploadMissingActivitySource(
                        activity,
                        classId,
                        uploadButton
                    );
                });

                actions.appendChild(uploadButton);
            }

            if (needsSourceFile) {
                const notice = document.createElement('p');

                notice.className = 'sq-activity-generation-message';
                notice.textContent =
                    'This draft needs a source document before questions can be generated.';

                notice.setAttribute('role', 'status');

                actions.appendChild(notice);
            }

            if (hasSavedQuestions) {
                const publishButton = document.createElement('button');

                publishButton.type = 'button';
                publishButton.className = 'sq-activity-publish';
                publishButton.textContent = 'Publish Activity';

                publishButton.disabled =
                    publishingActivityId !== null;

                publishButton.addEventListener('click', () => {
                    openPublishModal(activity, publishButton);
                });

                actions.appendChild(publishButton);
            }


            const deleteButton = document.createElement('button');

            deleteButton.type = 'button';
            deleteButton.className = 'sq-activity-delete';
            deleteButton.textContent = 'Delete';

            deleteButton.disabled =
                deletingActivityId !== null ||
                publishingActivityId !== null ||
                generatingActivityId !== null;

            deleteButton.addEventListener('click', () => {
                deleteDraftActivity(activity, classId, deleteButton);
            });

            actions.appendChild(deleteButton);


            card.append(actions, message);
        } else if (activity.status === 'published') {
            const viewButton = document.createElement('button');

            viewButton.type = 'button';
            viewButton.className = 'sq-activity-action';
            viewButton.textContent = 'View Saved Questions';

            viewButton.addEventListener('click', () => {
                viewSavedActivityQuestions(activity);
            });

            const scoresButton = document.createElement('button');

            scoresButton.type = 'button';
            scoresButton.className = 'sq-activity-action';
            scoresButton.textContent = 'View Scores';

            scoresButton.addEventListener('click', () => {
                openActivityScores(activity, classId, scoresButton);
            });

            actions.append(viewButton, scoresButton);
            card.appendChild(actions);
        }

        container.appendChild(card);
    }
}


function initializeActivityList() {
    const classPage = document.getElementById('class-page');
    const activitiesList = document.getElementById(
        'activities-list'
    );

    if (!classPage || !activitiesList) return;

    const classId = classPage.dataset.classId;

    let unsubscribeActivities = null;

    onAuthStateChanged(auth, (user) => {
        if (unsubscribeActivities) {
            unsubscribeActivities();
            unsubscribeActivities = null;
        }

        if (!user) {
            activitiesList.textContent =
                'Sign in to view class activities.';
            return;
        }

        activitiesList.textContent = 'Loading activities...';

        const activitiesRef = collection(
            db,
            'classes',
            classId,
            'activities'
        );

        unsubscribeActivities = onSnapshot(
            query(activitiesRef),
            (snapshot) => {
                const activities = snapshot.docs.map((doc) => ({
                    id: doc.id,
                    ...doc.data(),
                }));

                activities.sort((a, b) => {
                    const aTime =
                        a.createdAt?.toMillis?.() ?? 0;
                    const bTime =
                        b.createdAt?.toMillis?.() ?? 0;

                    return bTime - aTime;
                });

                renderGameActivities(
                    activitiesList,
                    activities,
                    classId
                );
            },
            (error) => {
                console.error(
                    'Failed to load game activities:',
                    error
                );

                activitiesList.textContent =
                    'Could not load activities. ' +
                    'Please check your permissions.';
            }
        );
    });
}

function initializeActivityForm() {
    const classPage = document.getElementById('class-page');
    const modal = document.getElementById('activity-modal');
    const form = document.getElementById('activity-form');

    if (!classPage || !modal || !form) return;

    const classId = classPage.dataset.classId;

    const createButton = document.getElementById(
        'create-activity-button'
    );

    console.log('Create Activity button:', createButton);
    console.log('Activity modal:', modal);
    console.log('Activity form:', form);

    const closeButton = document.getElementById(
        'close-activity-modal'
    );

    const cancelButton = document.getElementById(
        'cancel-activity-modal'
    );

    const methodSelect = document.getElementById(
        'activity-generation-method'
    );

    const topicSection = document.getElementById(
        'activity-topic-section'
    );

    const fileSection = document.getElementById(
        'activity-file-section'
    );

    const topicInput = document.getElementById('activity-topic');
    const fileInput = document.getElementById('activity-file');

    const saveButton = document.getElementById(
        'save-activity-button'
    );

    const message = document.getElementById(
        'activity-form-message'
    );

    let submitting = false;
    let signedInUser = null;
    let pendingFileDraftId = null;

    onAuthStateChanged(auth, (user) => {
        signedInUser = user;
    });

    function showMessage(text, state = 'error') {
        message.textContent = text;
        message.dataset.state = state;
        message.hidden = false;
    }

    function clearMessage() {
        message.textContent = '';
        message.hidden = true;
    }

    function updateGenerationMethod() {
        if (pendingFileDraftId) {
            methodSelect.value = 'file';
            methodSelect.disabled = true;
        } else {
            methodSelect.disabled = false;
        }

        const isTopic = methodSelect.value === 'topic';

        topicSection.hidden = !isTopic;
        fileSection.hidden = isTopic;

        topicInput.required = isTopic;
        fileInput.required = !isTopic;

        clearMessage();

        form.querySelectorAll(
            'input:not([type="file"]), select:not(#activity-generation-method), textarea'
        ).forEach((field) => {
            field.disabled = Boolean(pendingFileDraftId);
        });
    }

    function openModal() {
        if (!pendingFileDraftId) {
            form.reset();
        }

        clearMessage();
        updateGenerationMethod();

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('activity-title').focus();
    }

    function closeModal() {
        if (submitting) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        createButton.focus();
    }

    if (createButton) {
        createButton.addEventListener('click', () => {
            console.log('Create Activity clicked');
            openModal();
        });
    }
    closeButton?.addEventListener('click', closeModal);
    cancelButton?.addEventListener('click', closeModal);

    methodSelect.addEventListener(
        'change',
        updateGenerationMethod
    );

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            modal.classList.contains('is-open')
        ) {
            closeModal();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (submitting) return;

        clearMessage();

        const generationMethod = methodSelect.value;

        // File processing will be implemented separately.

        let selectedSourceFile = null;

        if (generationMethod === 'file') {
            selectedSourceFile = fileInput.files?.[0] ?? null;

            if (!selectedSourceFile) {
                showMessage('Please select a source document.');
                return;
            }

            const allowedExtensions = ['pdf', 'docx', 'txt'];

            const extension = selectedSourceFile.name
                .split('.')
                .pop()
                ?.toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                showMessage(
                    'Unsupported file type. Please select PDF, DOCX, or TXT.'
                );
                return;
            }

            const maxFileSize = 10 * 1024 * 1024;

            if (
                selectedSourceFile.size === 0 ||
                selectedSourceFile.size > maxFileSize
            ) {
                showMessage(
                    'The source document must be between 1 byte and 10 MB.'
                );
                return;
            }
        }


        if (!signedInUser) {
            showMessage('Please sign in again.');
            return;
        }


        const deadlineInput = document.getElementById('activity-deadline');
        const deadlineAt = deadlineInput?.value || null;

        if (deadlineAt) {
            const selectedDeadline = new Date(deadlineAt);

            if (
                Number.isNaN(selectedDeadline.getTime()) ||
                selectedDeadline.getTime() <= Date.now()
            ) {
                showMessage('Please select a future activity deadline.');
                return;
            }
        }


        const payload = {
            classId,
            title: document.getElementById(
                'activity-title'
            ).value.trim(),
            description: document.getElementById(
                'activity-description'
            ).value.trim(),
            gameType: document.getElementById(
                'activity-game-type'
            ).value,

            generationMethod,
            topic: generationMethod === 'topic'
                ? topicInput.value.trim()
                : '',
            maxAttempts: Number(
                document.getElementById(
                    'activity-max-attempts'
                ).value
            ),
            deadlineAt,
        };

        submitting = true;
        saveButton.disabled = true;
        saveButton.textContent = 'Saving Draft...';

        try {
            const idToken = await signedInUser.getIdToken();

            const csrfToken = document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

            if (!csrfToken) {
                throw new Error('CSRF token is missing.');
            }


            let activityId = pendingFileDraftId;

            if (!activityId) {
                const response = await fetch(
                    '/class/activities',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Authorization': `Bearer ${idToken}`,
                        },
                        body: JSON.stringify(payload),
                    }
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || 'Could not save activity.'
                    );
                }

                activityId = data.activityId;

                if (!activityId) {
                    throw new Error(
                        'The draft was created, but the server did not return an activity ID. ' +
                        'Check the activity list before retrying.'
                    );
                }

                if (generationMethod === 'file') {
                    pendingFileDraftId = activityId;
                    updateGenerationMethod();
                }
            }


            if (generationMethod === 'file') {
                saveButton.textContent = 'Uploading Document...';

                const uploadForm = new FormData();

                uploadForm.append('classId', classId);
                uploadForm.append('activityId', activityId);
                uploadForm.append('file', selectedSourceFile);

                const uploadResponse = await fetch(
                    '/class/activities/upload-source',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Authorization': `Bearer ${idToken}`,
                        },
                        body: uploadForm,
                    }
                );

                const uploadData = await uploadResponse.json();


                if (!uploadResponse.ok || !uploadData.success) {
                    throw new Error(
                        'Document upload failed: ' +
                        (uploadData.message || 'Unknown upload error.') +
                        ' Please retry the upload.'
                    );
                }

                pendingFileDraftId = null;

            }

            submitting = false;
            closeModal();

            showAlert(
                generationMethod === 'file'
                    ? 'Activity draft and source document saved successfully.'
                    : 'Activity draft saved successfully.'
            );

        } catch (error) {
            showMessage(
                error.message || 'Failed to save activity.'
            );
        } finally {
            submitting = false;
            saveButton.disabled = false;
            saveButton.textContent = pendingFileDraftId
                ? 'Retry Document Upload'
                : 'Save Draft';
        }

    });

    updateGenerationMethod();
}

function initializeGameActivities() {
    initializeActivityForm();
    initializeActivityList();
    initializeQuestionPreview();
    initializePublishModal();
    initializeScoreMonitoring();
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializeGameActivities,
        { once: true }
    );
} else {
    initializeGameActivities();
}
