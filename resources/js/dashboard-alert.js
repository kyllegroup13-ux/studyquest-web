const alertModal = document.querySelector('#dashboard-alert-modal');
const alertTitle = document.querySelector('#dashboard-alert-title');
const alertMessage = document.querySelector('#dashboard-alert-message');
const alertCloseButton = document.querySelector('#dashboard-alert-close');
const alertCancelButton = document.querySelector('#dashboard-alert-cancel');
const alertConfirmButton = document.querySelector('#dashboard-alert-confirm');

const promptQueue = [];
let activePrompt = null;

function openNextPrompt() {
    if (activePrompt || promptQueue.length === 0) {
        return;
    }

    activePrompt = promptQueue.shift();
    alertTitle.textContent = activePrompt.title;
    alertMessage.textContent = activePrompt.message;
    alertCancelButton.hidden = !activePrompt.confirmation;
    alertConfirmButton.hidden = !activePrompt.confirmation;
    alertCloseButton.hidden = activePrompt.confirmation;
    alertConfirmButton.textContent = activePrompt.confirmLabel;
    alertModal.hidden = false;
    (activePrompt.confirmation ? alertCancelButton : alertCloseButton).focus();
}

function closeActivePrompt(confirmed = false) {
    if (!activePrompt) {
        return;
    }

    const closedPrompt = activePrompt;
    activePrompt = null;
    alertModal.hidden = true;
    closedPrompt.resolve(closedPrompt.confirmation ? confirmed : undefined);

    window.setTimeout(() => {
        if (promptQueue.length > 0) {
            openNextPrompt();
        } else if (closedPrompt.returnFocus?.isConnected) {
            closedPrompt.returnFocus.focus();
        }
    }, 0);
}

function enqueuePrompt(prompt) {
    return new Promise(resolve => {
        promptQueue.push({
            ...prompt,
            message: String(prompt.message),
            resolve,
            returnFocus: document.activeElement
        });
        openNextPrompt();
    });
}

export function showDashboardAlert(message) {
    return enqueuePrompt({
        title: 'Notice',
        message,
        confirmation: false
    });
}

export function showDashboardConfirm(message, {
    title = 'Are you sure?',
    confirmLabel = 'Confirm'
} = {}) {
    return enqueuePrompt({
        title,
        message,
        confirmLabel,
        confirmation: true
    });
}

alertCloseButton?.addEventListener('click', () => closeActivePrompt());
alertCancelButton?.addEventListener('click', () => closeActivePrompt(false));
alertConfirmButton?.addEventListener('click', () => closeActivePrompt(true));

alertModal?.addEventListener('click', event => {
    if (event.target === alertModal) {
        closeActivePrompt(false);
    }
});

document.addEventListener('keydown', event => {
    if (!activePrompt) {
        return;
    }

    if (event.key === 'Tab') {
        event.preventDefault();
        if (activePrompt.confirmation) {
            (document.activeElement === alertCancelButton
                ? alertConfirmButton
                : alertCancelButton).focus();
        } else {
            alertCloseButton.focus();
        }
    } else if (event.key === 'Escape') {
        closeActivePrompt(false);
    }
});
