const modalQueue = [];
let modalDialog;
let modalTitle;
let modalMessage;
let cancelButton;
let confirmButton;
let confirmationResult = false;

function createModal() {
    modalDialog = document.createElement('dialog');
    modalDialog.className = 'sq-alert-modal';
    modalDialog.setAttribute('aria-labelledby', 'sq-alert-title');
    modalDialog.setAttribute('aria-describedby', 'sq-alert-message');

    const content = document.createElement('div');
    content.className = 'sq-alert-modal-content';

    const icon = document.createElement('span');
    icon.className = 'sq-alert-modal-icon';
    icon.setAttribute('data-modal-icon', '');
    icon.setAttribute('aria-hidden', 'true');

    const iconElement = document.createElement('i');
    icon.appendChild(iconElement);

    modalTitle = document.createElement('h2');
    modalTitle.className = 'sq-alert-modal-title';
    modalTitle.id = 'sq-alert-title';

    modalMessage = document.createElement('p');
    modalMessage.className = 'sq-alert-modal-message';
    modalMessage.id = 'sq-alert-message';

    const actions = document.createElement('div');
    actions.className = 'sq-alert-modal-actions';

    cancelButton = document.createElement('button');
    cancelButton.className = 'sq-alert-modal-button sq-alert-modal-button-secondary';
    cancelButton.type = 'button';
    cancelButton.addEventListener('click', () => modalDialog.close());

    confirmButton = document.createElement('button');
    confirmButton.className = 'sq-alert-modal-button';
    confirmButton.type = 'button';
    confirmButton.addEventListener('click', () => {
        confirmationResult = modalQueue[0]?.type === 'confirm';
        modalDialog.close();
    });

    actions.append(cancelButton, confirmButton);
    content.append(icon, modalTitle, modalMessage, actions);
    modalDialog.appendChild(content);
    modalDialog.addEventListener('cancel', () => {
        confirmationResult = false;
    });
    modalDialog.addEventListener('close', () => {
        const request = modalQueue.shift();

        if (request) {
            request.resolve(
                request.type === 'confirm'
                    ? confirmationResult
                    : undefined
            );
        }

        confirmationResult = false;
        showNextModal();
    });

    document.body.appendChild(modalDialog);
}

function showNextModal() {
    if (!modalQueue.length || modalDialog?.open) {
        return;
    }

    if (!modalDialog) {
        createModal();
    }

    const request = modalQueue[0];
    const isConfirmation = request.type === 'confirm';
    const icon = modalDialog.querySelector('[data-modal-icon] i');

    modalDialog.setAttribute(
        'role',
        isConfirmation ? 'alertdialog' : 'dialog'
    );
    modalTitle.textContent = request.title;
    modalMessage.textContent = request.message;
    cancelButton.hidden = !isConfirmation;
    cancelButton.textContent = request.cancelLabel;
    confirmButton.textContent = request.confirmLabel;
    confirmButton.classList.toggle(
        'sq-alert-modal-button-danger',
        isConfirmation
    );
    icon.className = isConfirmation
        ? 'fa-solid fa-trash'
        : 'fa-solid fa-circle-info';
    modalDialog.querySelector('[data-modal-icon]').classList.toggle(
        'sq-alert-modal-icon-danger',
        isConfirmation
    );

    modalDialog.showModal();
}

export function showAlert(message) {
    return new Promise(resolve => {
        modalQueue.push({
            type: 'alert',
            title: 'StudyQuest notice',
            message: String(message ?? ''),
            confirmLabel: 'Got it',
            resolve,
        });

        showNextModal();
    });
}

export function showConfirmation(
    message,
    {
        title = 'Confirm action',
        confirmLabel = 'Confirm',
        cancelLabel = 'Cancel',
    } = {}
) {
    return new Promise(resolve => {
        modalQueue.push({
            type: 'confirm',
            title: String(title),
            message: String(message ?? ''),
            confirmLabel: String(confirmLabel),
            cancelLabel: String(cancelLabel),
            resolve,
        });

        showNextModal();
    });
}
