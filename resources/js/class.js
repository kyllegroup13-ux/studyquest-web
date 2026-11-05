import { auth, db } from './firebase';
import { onAuthStateChanged, signOut } from 'firebase/auth';
import {
    addDoc,
    collection,
    deleteDoc,
    doc,
    getDoc,
    getDocs,
    serverTimestamp
} from 'firebase/firestore';
const classPage = document.querySelector('#class-page');
const classId = classPage?.dataset.classId;

const className = document.querySelector('#class-name');
const courseCode = document.querySelector('#course-code');
const classCode = document.querySelector('#class-code');
const yearLevel = document.querySelector('#year-level');
const courseInitials = document.querySelector('#course-initials');
const profileInitial = document.querySelector('#profile-initial');
const profileButton = document.querySelector('#profile-button');
const profileMenu = document.querySelector('#profile-menu');
const profileMenuInitial = document.querySelector('#profile-menu-initial');
const profileButtonImage = document.querySelector('#profile-button-image');
const profileMenuImage = document.querySelector('#profile-menu-image');
const profileName = document.querySelector('#profile-name');
const profileEmail = document.querySelector('#profile-email');
const studentsList = document.querySelector('#students-list');
const studentsLoading = document.querySelector('#students-loading');
const studentsEmpty = document.querySelector('#students-empty');
const studentCount = document.querySelector('#student-count');
const summaryStudentCount = document.querySelector('#summary-student-count');
const viewAllStudentsButton = document.querySelector('#view-all-students');
const viewStudentsLabel = document.querySelector('#view-students-label');
const viewStudentsIcon = document.querySelector('#view-students-icon');
const postsList = document.querySelector('#posts-list');
const postCount = document.querySelector('#post-count');

const modal = document.querySelector('#post-modal');
const addPostButton = document.querySelector('#add-post-button');
const closePostModalButton = document.querySelector('#close-post-modal');
const cancelPostModalButton = document.querySelector('#cancel-post-modal');
const addPostForm = document.querySelector('#add-post-form');
const postTitleInput = document.querySelector('#post-title');
const postDescriptionInput = document.querySelector('#post-description');
const postFileInput = document.querySelector('#post-file');
const selectedAttachmentsSection = document.querySelector('#selected-attachments-section');
const selectedAttachmentsList = document.querySelector('#selected-attachments-list');
const removeStudentModal = document.querySelector('#remove-student-modal');
const removeStudentName = document.querySelector('#remove-student-name');
const removeStudentError = document.querySelector('#remove-student-error');
const confirmRemoveStudentButton = document.querySelector('#confirm-remove-student');
const cancelRemoveStudentButton = document.querySelector('#cancel-remove-student');
const closeRemoveStudentModalButton = document.querySelector('#close-remove-student-modal');
const deleteAnnouncementModal = document.querySelector('#delete-announcement-modal');
const deleteAnnouncementTitle = document.querySelector('#delete-announcement-title');
const deleteAnnouncementError = document.querySelector('#delete-announcement-error');
const deleteAnnouncementErrorMessage = deleteAnnouncementError.querySelector('span');
const confirmDeleteAnnouncementButton = document.querySelector('#confirm-delete-announcement');
const cancelDeleteAnnouncementButton = document.querySelector('#cancel-delete-announcement');
const closeDeleteAnnouncementModalButton = document.querySelector('#close-delete-announcement-modal');

let currentUser = null;
let currentUserName = 'User';
let currentProfileImageUrl = '';
let currentClass = null;
let isTeacher = false;
let editingPostId = null;
let retainedAttachments = [];
let editingOriginalAttachments = [];
let selectedFiles = [];
let studentToRemove = null;
let removeStudentTrigger = null;
let announcementToDelete = null;
let deleteAnnouncementTrigger = null;

function firstInitial(name) {
    return (String(name || '').trim().charAt(0) || 'U').toUpperCase();
}

function formatDate(timestamp) {
    const date = timestamp?.toDate ? timestamp.toDate() : new Date();
    return date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
}

function updateCounter(inputId, countId, maxLength) {
    const input = document.getElementById(inputId);
    const counter = document.getElementById(countId);
    if (input && counter) counter.textContent = `${input.value.length} / ${maxLength}`;
}

function openDeleteAnnouncementModal(post, trigger) {
    announcementToDelete = post;
    deleteAnnouncementTrigger = trigger;
    deleteAnnouncementTitle.textContent = post.title || 'Untitled announcement';
    deleteAnnouncementError.hidden = true;
    deleteAnnouncementModal.classList.add('is-open');
    deleteAnnouncementModal.setAttribute('aria-hidden', 'false');
    confirmDeleteAnnouncementButton.focus();
}

function closeDeleteAnnouncementModal() {
    if (confirmDeleteAnnouncementButton.disabled) {
        return;
    }

    deleteAnnouncementModal.classList.remove('is-open');
    deleteAnnouncementModal.setAttribute('aria-hidden', 'true');
    deleteAnnouncementError.hidden = true;
    announcementToDelete = null;
    deleteAnnouncementTrigger?.focus();
    deleteAnnouncementTrigger = null;
}

async function confirmAnnouncementDeletion() {
    if (!currentUser || !isTeacher || !announcementToDelete) {
        return;
    }

    confirmDeleteAnnouncementButton.disabled = true;
    cancelDeleteAnnouncementButton.disabled = true;
    closeDeleteAnnouncementModalButton.disabled = true;
    confirmDeleteAnnouncementButton.textContent = 'Deleting...';
    deleteAnnouncementError.hidden = true;

    const deleted = await deletePost(announcementToDelete);

    confirmDeleteAnnouncementButton.disabled = false;
    cancelDeleteAnnouncementButton.disabled = false;
    closeDeleteAnnouncementModalButton.disabled = false;
    confirmDeleteAnnouncementButton.textContent = 'Delete announcement';

    if (deleted) {
        closeDeleteAnnouncementModal();
    }
}

function openRemoveStudentModal(studentId, fullName, trigger) {
    studentToRemove = studentId;
    removeStudentTrigger = trigger;
    removeStudentName.textContent = fullName;
    removeStudentError.hidden = true;
    removeStudentModal.classList.add('is-open');
    removeStudentModal.setAttribute('aria-hidden', 'false');
    confirmRemoveStudentButton.focus();
}

function closeRemoveStudentModal() {
    if (confirmRemoveStudentButton.disabled) {
        return;
    }

    removeStudentModal.classList.remove('is-open');
    removeStudentModal.setAttribute('aria-hidden', 'true');
    removeStudentError.hidden = true;
    studentToRemove = null;
    removeStudentTrigger?.focus();
    removeStudentTrigger = null;
}


async function confirmStudentRemoval() {
    if (!currentUser || !isTeacher || !studentToRemove) {
        return;
    }

    confirmRemoveStudentButton.disabled = true;
    cancelRemoveStudentButton.disabled = true;
    closeRemoveStudentModalButton.disabled = true;
    confirmRemoveStudentButton.textContent = 'Removing...';
    removeStudentError.hidden = true;

    let removed = false;

    try {
        const idToken = await currentUser.getIdToken();

        const response = await fetch(
            `/class/students/${encodeURIComponent(studentToRemove)}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${idToken}`,
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    classId
                })
            }
        );

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                result.message || 'Could not remove this student.'
            );
        }

        removed = true;

    } catch (error) {
        console.error('Could not remove student from class:', error);

        removeStudentError.textContent =
            error.message || 'Could not remove this student. Please try again.';

        removeStudentError.hidden = false;

    } finally {
        confirmRemoveStudentButton.disabled = false;
        cancelRemoveStudentButton.disabled = false;
        closeRemoveStudentModalButton.disabled = false;
        confirmRemoveStudentButton.textContent = 'Remove student';
    }

    if (removed) {
        closeRemoveStudentModal();

        try {
            await loadStudents();
        } catch (error) {
            console.error('Could not refresh students:', error);
            studentsLoading.textContent =
                'Student removed, but the list could not be refreshed.';
            studentsLoading.hidden = false;
        }
    }
}

function getPostAttachments(post) {
    if (Array.isArray(post.attachments) && post.attachments.length) {
        return post.attachments.map(file => ({
            fileName: file.fileName || '',
            fileUrl: file.fileUrl || '',
            filePublicId: file.filePublicId || ''
        }));
    }

    if (post.fileName && post.fileUrl) {
        return [{
            fileName: post.fileName,
            fileUrl: post.fileUrl,
            filePublicId: post.filePublicId || ''
        }];
    }

    return [];
}

function getAttachmentIconClass(fileName) {
    const extension = fileName.split('.').pop()?.toLowerCase() || '';
    const iconClass = extension === 'pdf'
        ? 'fa-file-pdf'
        : ['doc', 'docx'].includes(extension)
            ? 'fa-file-word'
            : ['ppt', 'pptx'].includes(extension)
                ? 'fa-file-powerpoint'
                : ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)
                    ? 'fa-file-image'
                    : 'fa-file';

    return { extension, iconClass };
}

function formatFileSize(size) {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function createAttachmentCard(fileName, details, onRemove) {
    const card = document.createElement('div');
    card.className = 'sq-file-card';

    const { extension, iconClass } = getAttachmentIconClass(fileName);
    const icon = document.createElement('span');
    icon.className = `sq-attachment-icon sq-attachment-icon-${extension || 'generic'}`;
    icon.setAttribute('aria-hidden', 'true');

    const fileIcon = document.createElement('i');
    fileIcon.className = `fa-solid ${iconClass}`;
    icon.append(fileIcon);

    const info = document.createElement('div');
    info.className = 'sq-file-card-info';

    const name = document.createElement('span');
    name.className = 'sq-file-card-name';
    name.textContent = fileName;

    const description = document.createElement('span');
    description.className = 'sq-file-card-details';
    description.textContent = details;
    info.append(name, description);
    card.append(icon, info);

    if (onRemove) {
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'sq-file-card-remove';
        removeButton.setAttribute('aria-label', `Remove ${fileName}`);
        removeButton.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        removeButton.addEventListener('click', onRemove);
        card.append(removeButton);
    }

    return card;
}

function renderExistingAttachments() {
    const section = document.querySelector('#existing-attachments-section');
    const list = document.querySelector('#existing-attachments-list');

    if (!section || !list) return;

    list.replaceChildren();
    section.hidden = !editingPostId;

    if (!editingPostId) return;

    if (retainedAttachments.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'sq-attachments-empty';
        empty.textContent = 'No existing attachments.';
        list.append(empty);
        return;
    }

    retainedAttachments.forEach((file, index) => {
        const card = createAttachmentCard(file.fileName, 'Attached to this post', () => {
            retainedAttachments.splice(index, 1);
            renderExistingAttachments();
        });

        list.append(card);
    });
}

function openPostModal(post = null) {
    if (!isTeacher) return;

    editingPostId = post?.id || null;

    editingOriginalAttachments = post
        ? getPostAttachments(post)
        : [];

    retainedAttachments = editingOriginalAttachments.map(file => ({ ...file }));

    renderExistingAttachments();

    postTitleInput.value = post?.title || '';
    postDescriptionInput.value = post?.description || '';

    document.querySelector('#post-modal-title').textContent =
        editingPostId ? 'Edit Post' : 'Add Post';

    document.querySelector('#submit-post-button').textContent =
        editingPostId ? 'Save Changes' : '+ Add Post';

    const uploadWrapper = document.querySelector('.sq-upload-wrapper');

    if (uploadWrapper) {
        uploadWrapper.hidden = false;
    }

    updateCounter('post-title', 'post-title-count', 100);
    updateCounter('post-description', 'post-description-count', 200);

    modal?.classList.add('is-open');
    modal?.setAttribute('aria-hidden', 'false');

    window.setTimeout(() => postTitleInput?.focus(), 50);
}

function closePostModal() {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');

    // Reset the form.
    addPostForm?.reset();

    // Reset editing state.
    editingPostId = null;

    retainedAttachments = [];
    editingOriginalAttachments = [];
    selectedFiles = [];

    renderExistingAttachments();

    // Restore the original modal title.
    document.querySelector('#post-modal-title').textContent = 'Add Post';

    // Restore the original submit button.
    document.querySelector('#submit-post-button').textContent = '+ Add Post';

    // Show the upload section again.
    const uploadWrapper = document.querySelector('.sq-upload-wrapper');

    if (uploadWrapper) {
        uploadWrapper.hidden = false;
    }

    renderSelectedAttachments();

    // Reset character counters.
    updateCounter('post-title', 'post-title-count', 100);
    updateCounter('post-description', 'post-description-count', 200);
}

function renderSelectedAttachments() {
    if (!selectedAttachmentsSection || !selectedAttachmentsList) return;

    selectedAttachmentsList.replaceChildren();
    selectedAttachmentsSection.hidden = selectedFiles.length === 0;

    selectedFiles.forEach((file, index) => {
        const details = `${file.name.split('.').pop()?.toUpperCase() || 'FILE'} - ${formatFileSize(file.size)}`;
        const card = createAttachmentCard(file.name, details, () => {
            selectedFiles.splice(index, 1);
            syncSelectedFiles();
            renderSelectedAttachments();
        });

        selectedAttachmentsList.append(card);
    });
}

function syncSelectedFiles() {
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    postFileInput.files = dataTransfer.files;
}

function addSelectedFiles(files) {
    const existingFiles = new Set(
        selectedFiles.map(file => `${file.name}:${file.size}:${file.lastModified}`)
    );

    files.forEach(file => {
        const key = `${file.name}:${file.size}:${file.lastModified}`;
        if (!existingFiles.has(key)) {
            selectedFiles.push(file);
            existingFiles.add(key);
        }
    });

    syncSelectedFiles();
    renderSelectedAttachments();
}

function setInitial(element, name) {
    if (element) element.textContent = firstInitial(name);
}

function setProfileImage(url) {
    [profileButtonImage, profileMenuImage].forEach(image => {
        if (!image) return;

        if (url) {
            image.src = url;
        } else {
            image.removeAttribute('src');
        }

        image.hidden = !url;
        image.onerror = () => {
            image.hidden = true;
            if (image === profileButtonImage) {
                profileInitial.hidden = false;
            } else if (profileMenuInitial) {
                profileMenuInitial.hidden = false;
            }
        };
    });

    if (profileInitial) profileInitial.hidden = Boolean(url);
    if (profileMenuInitial) profileMenuInitial.hidden = Boolean(url);
}

function renderPost(post) {
    const article = document.createElement('article');
    article.className = 'sq-post-card';

    const author = document.createElement('div');
    author.className = 'sq-post-author';

    const avatar = document.createElement('div');
    avatar.className = 'sq-avatar';
    avatar.setAttribute('aria-hidden', 'true');

    const initial = document.createElement('span');
    initial.textContent = firstInitial(post.authorName);
    avatar.append(initial);

    const authorImageUrl = post.authorId === currentUser?.uid
        ? currentProfileImageUrl
        : post.authorImageUrl;

    if (authorImageUrl) {
        initial.hidden = true;

        const image = document.createElement('img');
        image.src = authorImageUrl;
        image.alt = '';
        image.addEventListener('error', () => {
            image.remove();
            initial.hidden = false;
        }, { once: true });
        avatar.append(image);
    }

    const authorDetails = document.createElement('div');
    const authorName = document.createElement('p');
    authorName.className = 'sq-author-name';
    authorName.textContent = post.authorName || 'Teacher';
    const date = document.createElement('p');
    date.className = 'sq-date';
    date.textContent = formatDate(post.createdAt);
    authorDetails.append(authorName, date);
    author.append(avatar, authorDetails);

    // Show post management options only to the teacher who created the post.
    if (isTeacher && post.authorId === currentUser?.uid) {
        const actions = document.createElement('div');
        actions.className = 'sq-post-menu';

        const menuButton = document.createElement('button');
        menuButton.type = 'button';
        menuButton.className = 'sq-post-menu-button';
        menuButton.innerHTML = '<i class="fa-solid fa-ellipsis"></i>';
        menuButton.setAttribute('aria-label', 'Post options');
        menuButton.setAttribute('aria-expanded', 'false');

        const menu = document.createElement('div');
        menu.className = 'sq-post-menu-dropdown';
        menu.hidden = true;

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.innerHTML = '<i class="fa-solid fa-pen"></i> Edit';

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'sq-post-delete';
        deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';

        menuButton.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            menuButton.setAttribute('aria-expanded', String(!menu.hidden));
        });

        editButton.addEventListener('click', () => {
            menu.hidden = true;
            menuButton.setAttribute('aria-expanded', 'false');
            openPostModal(post);
        });

        deleteButton.addEventListener('click', async () => {
            menu.hidden = true;
            menuButton.setAttribute('aria-expanded', 'false');
            openDeleteAnnouncementModal(post, deleteButton);
        });

        menu.append(editButton, deleteButton);
        actions.append(menuButton, menu);
        author.append(actions);
    }

    const title = document.createElement('h3');
    title.className = 'sq-post-title';
    title.textContent = post.title || 'Untitled post';

    const description = document.createElement('p');
    description.className = 'sq-post-description';
    description.textContent = post.description || 'No description provided.';

    article.append(author, title, description);

    const attachments = Array.isArray(post.attachments) && post.attachments.length
        ? post.attachments
        : (post.fileName ? [{ fileName: post.fileName, fileUrl: post.fileUrl }] : []);

    if (attachments.length) {
        const attachmentList = document.createElement('div');
        attachmentList.className = 'sq-post-attachments';

        attachments.forEach(file => {
            const attachment = document.createElement('div');
            attachment.className = 'sq-post-attachment';
            const icon = document.createElement('span');
            const extension = file.fileName?.split('.').pop()?.toLowerCase() || '';
            const iconClass = extension === 'pdf'
                ? 'fa-file-pdf'
                : ['doc', 'docx'].includes(extension)
                    ? 'fa-file-word'
                    : ['ppt', 'pptx'].includes(extension)
                        ? 'fa-file-powerpoint'
                        : ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)
                            ? 'fa-file-image'
                            : 'fa-file';
            icon.className = `sq-attachment-icon sq-attachment-icon-${extension || 'generic'}`;
            icon.setAttribute('aria-hidden', 'true');
            const fileIcon = document.createElement('i');
            fileIcon.className = `fa-solid ${iconClass}`;
            icon.append(fileIcon);
            const link = document.createElement('a');
            link.textContent = file.fileName;
            link.href = file.fileUrl || '#';
            if (file.fileUrl) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            } else {
                link.addEventListener('click', event => event.preventDefault());
            }
            attachment.append(icon, link);
            attachmentList.append(attachment);
        });

        article.append(attachmentList);
    }

    return article;
}

async function loadPosts() {
    postsList.innerHTML = '<p class="sq-post-empty">Loading posts...</p>';
    const snapshot = await getDocs(collection(db, 'classes', classId, 'posts'));
    const posts = snapshot.docs.map(item => ({ id: item.id, ...item.data() }));
    postCount.textContent = String(posts.length);
    posts.sort((a, b) => {
        const aTime = a.createdAt?.toMillis?.() || 0;
        const bTime = b.createdAt?.toMillis?.() || 0;
        return bTime - aTime;
    });
    postsList.innerHTML = '';
    if (!posts.length) {
        postsList.innerHTML = '<div class="sq-post-empty">No posts yet. Share a file or update with your class.</div>';
        return;
    }
    posts.forEach(post => postsList.append(renderPost(post)));
}

async function loadStudents() {
    studentsLoading.hidden = false;
    studentsList.innerHTML = '';
    const snapshot = await getDocs(collection(db, 'classes', classId, 'students'));
    studentCount.textContent = String(snapshot.size);
    summaryStudentCount.textContent = String(snapshot.size);
    studentsLoading.hidden = true;
    viewAllStudentsButton.hidden = !isTeacher || snapshot.empty;
    document.querySelector('.sq-students-panel').classList.remove('is-expanded');
    viewAllStudentsButton.setAttribute('aria-expanded', 'false');
    viewStudentsLabel.textContent = 'View all students';
    viewStudentsIcon.className = 'fa-solid fa-arrow-right';

    if (!isTeacher) {
        // Enrolled students can view class posts; student roster is teacher-only.
        studentsEmpty.hidden = true;
        studentsList.innerHTML = '<p class="sq-student-date">Student list is visible to the teacher.</p>';
        return;
    }

    if (snapshot.empty) {
        studentsEmpty.hidden = false;
        return;
    }

    studentsEmpty.hidden = true;
    snapshot.forEach(studentDoc => {
        const student = studentDoc.data();
        const fullName = student.fullName || student.name || 'Student';
        const row = document.createElement('div');
        row.className = 'sq-student-row';

        const avatar = document.createElement('div');
        avatar.className = 'sq-student-avatar';
        avatar.setAttribute('aria-hidden', 'true');

        const initial = document.createElement('span');
        initial.textContent = firstInitial(fullName);
        avatar.append(initial);

        if (student.profileImageUrl) {
            initial.hidden = true;

            const image = document.createElement('img');
            image.src = student.profileImageUrl;
            image.alt = '';
            image.addEventListener('error', () => {
                image.remove();
                initial.hidden = false;
            }, { once: true });
            avatar.append(image);
        }

        const details = document.createElement('div');
        details.className = 'sq-student-details';
        const name = document.createElement('p');
        name.className = 'sq-student-name';
        name.textContent = fullName;
        const email = document.createElement('p');
        email.className = 'sq-student-date';
        email.textContent = student.email || '';
        details.append(name, email);

        const removeButton = document.createElement('button');
        removeButton.className = 'sq-remove-student';
        removeButton.type = 'button';
        removeButton.textContent = 'Remove';
        removeButton.setAttribute('aria-label', `Remove ${fullName} from class`);
        removeButton.addEventListener('click', () => {
            openRemoveStudentModal(studentDoc.id, fullName, removeButton);
        });

        row.append(avatar, details, removeButton);
        studentsList.append(row);
    });
}

async function uploadAttachment(file) {
    const idToken = await currentUser.getIdToken();
    const body = new FormData();
    body.append('class_id', classId);
    body.append('file', file);

    const response = await fetch('/class/attachments', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${idToken}`,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(validationMessage || data.message || 'Could not upload the attachment.');
    }
    return data;
}

async function uploadAttachments(files) {
    const attachments = [];
    for (const file of files) {
        attachments.push(await uploadAttachment(file));
    }
    return attachments;
}

async function deletePost(post) {
    if (!currentUser || !isTeacher) {
        return false;
    }

    let postDeleted = false;

    try {
        // Get the teacher's Firebase ID token.
        const idToken = await currentUser.getIdToken();

        // Send the deletion request to Laravel.
        const response = await fetch(
            `/class/posts/${encodeURIComponent(post.id)}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${idToken}`,
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    classId
                })
            }
        );

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                result.message || 'Could not delete the announcement.'
            );
        }

        postDeleted = true;
        console.log('Announcement deleted:', post.id);

        // Refresh the displayed announcements.
        await loadPosts();
        return true;

    } catch (error) {
        if (postDeleted) {
            console.error('Could not refresh posts after deletion:', error);
            postsList.innerHTML =
                '<div class="sq-post-empty">Announcement deleted, but the post list could not be refreshed. Reload the page to see the latest posts.</div>';
            return true;
        }

        console.error('Post deletion failed:', error);
        deleteAnnouncementErrorMessage.textContent =
            error.message || 'Could not delete this announcement. Please try again.';
        deleteAnnouncementError.hidden = false;
        return false;
    }
}

async function submitPost(event) {
    event.preventDefault();

    if (!currentUser || !isTeacher) {
        alert('Only the class teacher can publish posts.');
        return;
    }

    const title = postTitleInput.value.trim();
    const description = postDescriptionInput.value.trim();
    const files = Array.from(postFileInput.files || []);

    if (editingPostId) {
        const submitButton = addPostForm.querySelector(
            'button[type="submit"]'
        );

        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        try {
            const files = Array.from(postFileInput.files || []);

            const oversizedFiles = files.filter(
                file => file.size > 10 * 1024 * 1024
            );

            if (oversizedFiles.length) {
                throw new Error('Each attachment must be 10 MB or smaller.');
            }

            if (!title) {
                throw new Error('Post title is required.');
            }

            // Upload newly selected files only when saving.
            const newAttachments = files.length
                ? await uploadAttachments(files)
                : [];

            // Combine retained and newly uploaded attachments.
            const attachments = [
                ...retainedAttachments,
                ...newAttachments
            ];

            const idToken = await currentUser.getIdToken();

            const response = await fetch(
                `/class/posts/${encodeURIComponent(editingPostId)}`,
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        Authorization: `Bearer ${idToken}`,
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        classId,
                        title,
                        description,
                        attachments
                    })
                }
            );

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || 'Could not update the post.');
            }

            await loadPosts();
            closePostModal();

        } catch (error) {
            console.error('Post update failed:', error);
            alert(error.message || 'Could not update the post.');

        } finally {
            submitButton.disabled = false;

            if (editingPostId) {
                submitButton.textContent = 'Save Changes';
            }
        }

        return;
    }

    if (!title) {
        postTitleInput.focus();
        return;
    }

    const oversizedFiles = files.filter(file => file.size > 10 * 1024 * 1024);
    if (oversizedFiles.length) {
        alert(`Each file must be 10 MB or smaller. Remove: ${oversizedFiles.map(file => file.name).join(', ')}`);
        return;
    }

    const submitButton = addPostForm.querySelector(
        'button[type="submit"]'
    );

    const originalText = submitButton.textContent;

    submitButton.disabled = true;
    submitButton.textContent = 'Publishing...';

    try {
        // 1. Upload attachments using the existing Cloudinary endpoint.
        const attachments = files.length ? await uploadAttachments(files) : [];
        const primaryAttachment = attachments[0] || {};

        // 2. Obtain the signed-in teacher's Firebase ID token.
        const idToken = await currentUser.getIdToken();

        // 3. Get Laravel's CSRF token.
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error(
                'CSRF token is missing. Please refresh the page.'
            );
        }

        // 4. Publish through the Laravel backend.
        const response = await fetch('/class/posts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${idToken}`,
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                classId,
                title,
                description,
                fileName: primaryAttachment.fileName || '',
                filePublicId: primaryAttachment.filePublicId || '',
                fileUrl: primaryAttachment.fileUrl || '',
                attachments
            })
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message || 'Could not publish the announcement.'
            );
        }

        // 5. Confirm publication.
        console.log('Announcement published:', result.postId);

        if (result.notificationSent) {
            console.log('FCM notification accepted by Firebase.');
        } else {
            console.warn(
                'Announcement saved, but notification sending failed.'
            );
        }

        // 6. Refresh class posts and close the modal.
        await loadPosts();
        closePostModal();

    } catch (error) {
        console.error('Could not publish class post:', error);

        alert(
            error.message ||
            'Could not publish this post. Please try again.'
        );

    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
}


document.addEventListener('input', event => {
    if (event.target.id === 'student-search') {
        const search = event.target.value.trim().toLowerCase();
        document.querySelectorAll('#students-list .sq-student-row').forEach(row => {
            row.hidden = !row.textContent.toLowerCase().includes(search);
        });
    } else if (event.target.id === 'post-title') {
        updateCounter('post-title', 'post-title-count', 100);
    } else if (event.target.id === 'post-description') {
        updateCounter('post-description', 'post-description-count', 200);
    }
});

addPostButton?.addEventListener('click', () => openPostModal());
viewAllStudentsButton?.addEventListener('click', () => {
    const isExpanded = document.querySelector('.sq-students-panel').classList.toggle('is-expanded');
    viewAllStudentsButton.setAttribute('aria-expanded', String(isExpanded));
    viewStudentsLabel.textContent = isExpanded ? 'Show fewer students' : 'View all students';
    viewStudentsIcon.className = isExpanded ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-right';
});
confirmRemoveStudentButton?.addEventListener('click', confirmStudentRemoval);
confirmDeleteAnnouncementButton?.addEventListener('click', confirmAnnouncementDeletion);
cancelRemoveStudentButton?.addEventListener('click', closeRemoveStudentModal);
closeRemoveStudentModalButton?.addEventListener('click', closeRemoveStudentModal);
cancelDeleteAnnouncementButton?.addEventListener('click', closeDeleteAnnouncementModal);
closeDeleteAnnouncementModalButton?.addEventListener('click', closeDeleteAnnouncementModal);
removeStudentModal?.addEventListener('click', event => {
    if (event.target === removeStudentModal) {
        closeRemoveStudentModal();
    }
});
deleteAnnouncementModal?.addEventListener('click', event => {
    if (event.target === deleteAnnouncementModal) {
        closeDeleteAnnouncementModal();
    }
});
closePostModalButton?.addEventListener('click', closePostModal);
cancelPostModalButton?.addEventListener('click', closePostModal);
modal?.addEventListener('click', event => {
    if (event.target === modal) closePostModal();
});
postFileInput?.addEventListener('change', () => {
    addSelectedFiles(Array.from(postFileInput.files || []));
});
addPostForm?.addEventListener('submit', submitPost);

profileButton?.addEventListener('click', () => {
    const isOpening = profileMenu.hidden;
    profileMenu.hidden = !isOpening;
    profileButton.setAttribute('aria-expanded', String(isOpening));
});

document.querySelector('#display-profile-button')?.addEventListener('click', () => {
    window.location.assign('/dashboard?profile=display');
});

document.querySelector('#edit-profile-button')?.addEventListener('click', () => {
    window.location.assign('/dashboard?profile=edit');
});

document.querySelector('#logout-button')?.addEventListener('click', async () => {
    try {
        await signOut(auth);
        window.location.replace('/');
    } catch (error) {
        console.error('Could not log out:', error);
        alert('Could not log out. Please try again.');
    }
});

document.addEventListener('click', event => {
    if (
        !profileMenu?.hidden &&
        !profileMenu?.contains(event.target) &&
        !profileButton?.contains(event.target)
    ) {
        profileMenu.hidden = true;
        profileButton?.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && deleteAnnouncementModal?.classList.contains('is-open')) {
        closeDeleteAnnouncementModal();
    }

    if (event.key === 'Escape' && removeStudentModal && removeStudentModal.classList.contains('is-open')) {
        closeRemoveStudentModal();
    }

    if (event.key === 'Escape' && profileMenu && !profileMenu.hidden) {
        profileMenu.hidden = true;
        profileButton?.setAttribute('aria-expanded', 'false');
        profileButton?.focus();
    }
});

onAuthStateChanged(auth, async user => {
    if (!user) {
        window.location.replace('/');
        return;
    }
    if (!classId) {
        console.error('Class ID is missing.');
        return;
    }

    currentUser = user;
    try {
        const [classSnapshot, userSnapshot] = await Promise.all([
            getDoc(doc(db, 'classes', classId)),
            getDoc(doc(db, 'users', user.uid))
        ]);

        if (!classSnapshot.exists()) {
            className.textContent = 'Class not found';
            return;
        }

        currentClass = classSnapshot.data();
        isTeacher = currentClass.teacherId === user.uid;

        const userData = userSnapshot.exists() ? userSnapshot.data() : {};
        currentUserName = userData.name || user.displayName || user.email?.split('@')[0] || 'User';
        currentProfileImageUrl = userData.profileImageUrl || user.photoURL || '';
        setInitial(profileInitial, currentUserName);
        setInitial(profileMenuInitial, currentUserName);
        profileName.textContent = currentUserName;
        profileEmail.textContent = user.email || '';
        setProfileImage(currentProfileImageUrl);

        const enrollmentSnapshot = isTeacher
            ? null
            : await getDoc(doc(db, 'classes', classId, 'students', user.uid));

        if (!isTeacher && !enrollmentSnapshot?.exists()) {
            className.textContent = 'You are not enrolled in this class';
            postsList.innerHTML = '<p class="sq-post-empty">Ask your teacher to enroll your account before viewing class posts.</p>';
            document.querySelector('.sq-students-panel')?.setAttribute('hidden', '');
            addPostButton?.setAttribute('hidden', '');
            return;
        }

        className.textContent = currentClass.className || 'Unnamed Class';
        courseCode.textContent = currentClass.subjectCode || '—';
        classCode.textContent = currentClass.classCode || '—';
        yearLevel.textContent = currentClass.yearLevel || '—';
        setInitial(courseInitials, currentClass.className || 'Class');

        if (!isTeacher) {
            addPostButton?.setAttribute('hidden', '');
            studentsLoading.hidden = true;
        }

        await Promise.all([
            loadPosts().catch(error => {
                console.error('Could not load posts:', error);
                postsList.innerHTML = '<div class="sq-post-empty">Could not load posts. Make sure the latest Firestore rules are deployed.</div>';
            }),
            loadStudents().catch(error => {
                console.error('Could not load students:', error);
                if (studentsLoading) studentsLoading.textContent = 'Could not load students.';
            })
        ]);
    } catch (error) {
        console.error('Could not load class:', error);
        className.textContent = 'Could not load class';
        if (studentsLoading) studentsLoading.textContent = 'Could not load students. Check Firebase rules.';
    }
});

updateCounter('post-title', 'post-title-count', 100);
updateCounter('post-description', 'post-description-count', 200);
