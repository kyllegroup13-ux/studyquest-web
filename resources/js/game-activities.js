import { onAuthStateChanged } from 'firebase/auth';
import { auth, db } from './firebase';
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
        window.alert('Class ID is missing.');
        return;
    }

    const user = auth.currentUser;

    if (!user) {
        window.alert('Please sign in again.');
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

        window.alert(
            'Successfully saved 10 questions to Firestore. ' +
            'The activity remains a draft.'
        );

        generatedActivityPreview = null;

        savingActivityQuestions = false;
        closeQuestionPreview();
    } catch (error) {
        window.alert(
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
        window.alert(
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
        window.alert('Unsupported game type.');
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

        window.alert('Activity published successfully.');
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

        const hasSavedQuestions =
            Number(activity.questionCount ?? 0) === 10;

        status.classList.add(
            isDraft ? 'sq-activity-status-draft'
                : 'sq-activity-status-published'
        );
        status.textContent = isDraft
            ? 'Draft'
            : activity.status === 'published'
                ? 'Published'
                : activity.status || 'Unknown';

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
                publishingActivityId !== null;

            questionAction.textContent = hasSavedQuestions
                ? 'View Saved Questions'
                : isGenerating
                    ? 'Generating Questions...'
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

            card.append(actions, message);
        } else if (activity.status === 'published') {
            const viewButton = document.createElement('button');

            viewButton.type = 'button';
            viewButton.className = 'sq-activity-action';
            viewButton.textContent = 'View Saved Questions';

            viewButton.addEventListener('click', () => {
                viewSavedActivityQuestions(activity);
            });

            actions.appendChild(viewButton);
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
        const isTopic = methodSelect.value === 'topic';

        topicSection.hidden = !isTopic;
        fileSection.hidden = isTopic;

        topicInput.required = isTopic;
        fileInput.required = !isTopic;

        clearMessage();
    }

    function openModal() {
        form.reset();
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
        if (generationMethod === 'file') {
            showMessage(
                'File-based activity creation is not available yet. ' +
                'Please select Enter Topic.',
                'info'
            );
            return;
        }

        if (!signedInUser) {
            showMessage('Please sign in again.');
            return;
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
            topic: topicInput.value.trim(),
            maxAttempts: Number(
                document.getElementById(
                    'activity-max-attempts'
                ).value
            ),
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

            if (!response.ok) {
                throw new Error(
                    data.message || 'Could not save activity.'
                );
            }

            showMessage(
                'Activity draft saved successfully.',
                'success'
            );

            closeModal();
        } catch (error) {
            showMessage(
                error.message || 'Failed to save activity.'
            );
        } finally {
            submitting = false;
            saveButton.disabled = false;
            saveButton.textContent = 'Save Draft';
        }
    });

    updateGenerationMethod();
}

function initializeGameActivities() {
    initializeActivityForm();
    initializeActivityList();
    initializeQuestionPreview();
    initializePublishModal();
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
