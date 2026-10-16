import { auth, db } from './firebase';
import {
    onAuthStateChanged
} from 'firebase/auth';
import {
    addDoc,
    collection,
    doc,
    getDoc,
    getDocs,
    serverTimestamp
} from 'firebase/firestore';
const classPage = document.querySelector('#class-page');
const classId = classPage?.dataset.classId;

const className = document.querySelector('#class-name');
const subjectCode = document.querySelector('#subject-code');
const courseInitials = document.querySelector('#course-initials');
const profileInitial = document.querySelector('#profile-initial');
const studentsList = document.querySelector('#students-list');
const studentsLoading = document.querySelector('#students-loading');
const studentsEmpty = document.querySelector('#students-empty');
const studentCount = document.querySelector('#student-count');
const postsList = document.querySelector('#posts-list');

const modal = document.querySelector('#post-modal');
const addPostButton = document.querySelector('#add-post-button');
const closePostModalButton = document.querySelector('#close-post-modal');
const cancelPostModalButton = document.querySelector('#cancel-post-modal');
const addPostForm = document.querySelector('#add-post-form');
const postTitleInput = document.querySelector('#post-title');
const postDescriptionInput = document.querySelector('#post-description');
const postFileInput = document.querySelector('#post-file');
const postFileName = document.querySelector('#post-file-name');
const postUploadBox = document.querySelector('.sq-upload-box');
const postUploadContent = document.querySelector('#post-upload-content');
const postImagePreview = document.querySelector('#post-image-preview');
const postUploadImageReplace = document.querySelector('#post-upload-image-replace');

let currentUser = null;
let currentUserName = 'User';
let currentClass = null;
let isTeacher = false;
let imagePreviewUrl = null;

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

function openPostModal() {
    if (!isTeacher) return;
    modal?.classList.add('is-open');
    modal?.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => postTitleInput?.focus(), 50);
}

function closePostModal() {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');
    addPostForm?.reset();
    if (postFileName) postFileName.textContent = 'No file selected';
    clearPostImagePreview();
    updateCounter('post-title', 'post-title-count', 100);
    updateCounter('post-description', 'post-description-count', 200);
}

function clearPostImagePreview() {
    if (imagePreviewUrl) {
        URL.revokeObjectURL(imagePreviewUrl);
        imagePreviewUrl = null;
    }

    postImagePreview?.removeAttribute('src');
    if (postImagePreview) postImagePreview.hidden = true;
    if (postUploadImageReplace) postUploadImageReplace.hidden = true;
    postUploadContent?.removeAttribute('hidden');
    postUploadBox?.classList.remove('has-image');
}

function updatePostFilePreview() {
    const file = postFileInput.files?.[0];
    postFileName.textContent = file ? file.name : 'No file selected';
    clearPostImagePreview();

    if (!file?.type.startsWith('image/')) return;

    imagePreviewUrl = URL.createObjectURL(file);
    postImagePreview.src = imagePreviewUrl;
    postImagePreview.hidden = false;
    postUploadContent.hidden = true;
    postUploadImageReplace.hidden = false;
    postUploadBox.classList.add('has-image');
}

function setInitial(element, name) {
    if (element) element.textContent = firstInitial(name);
}

function renderPost(post) {
    const article = document.createElement('article');
    article.className = 'sq-post-card';

    const author = document.createElement('div');
    author.className = 'sq-post-author';

    const avatar = document.createElement('div');
    avatar.className = 'sq-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = firstInitial(post.authorName);

    const authorDetails = document.createElement('div');
    const authorName = document.createElement('p');
    authorName.className = 'sq-author-name';
    authorName.textContent = post.authorName || 'Teacher';
    const date = document.createElement('p');
    date.className = 'sq-date';
    date.textContent = formatDate(post.createdAt);
    authorDetails.append(authorName, date);
    author.append(avatar, authorDetails);

    const title = document.createElement('h3');
    title.className = 'sq-post-title';
    title.textContent = post.title || 'Untitled post';

    const description = document.createElement('p');
    description.className = 'sq-post-description';
    description.textContent = post.description || 'No description provided.';

    article.append(author, title, description);

    if (post.fileName) {
        const attachment = document.createElement('div');
        attachment.className = 'sq-post-attachment';
        const icon = document.createElement('span');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '📎';
        const link = document.createElement('a');
        link.textContent = post.fileName;
        link.href = post.fileUrl || '#';
        if (post.fileUrl) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        } else {
            link.addEventListener('click', event => event.preventDefault());
        }
        attachment.append(icon, link);
        article.append(attachment);
    }

    return article;
}

async function loadPosts() {
    postsList.innerHTML = '<p class="sq-post-empty">Loading posts...</p>';
    const snapshot = await getDocs(collection(db, 'classes', classId, 'posts'));
    const posts = snapshot.docs.map(item => ({ id: item.id, ...item.data() }));
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
    studentsLoading.hidden = true;

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
        const row = document.createElement('div');
        row.className = 'sq-student-row';

        const avatar = document.createElement('div');
        avatar.className = 'sq-student-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = firstInitial(student.name);

        const details = document.createElement('div');
        const name = document.createElement('p');
        name.className = 'sq-student-name';
        name.textContent = student.name || 'Student';
        const email = document.createElement('p');
        email.className = 'sq-student-date';
        email.textContent = student.email || '';
        details.append(name, email);
        row.append(avatar, details);
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

async function submitPost(event) {
    event.preventDefault();
    if (!currentUser || !isTeacher) {
        alert('Only the class teacher can publish posts.');
        return;
    }

    const title = postTitleInput.value.trim();
    const description = postDescriptionInput.value.trim();
    const file = postFileInput.files?.[0];

    if (!title) {
        postTitleInput.focus();
        return;
    }
    if (file && file.size > 10 * 1024 * 1024) {
        alert('The selected file is larger than 10 MB.');
        return;
    }

    const submitButton = addPostForm.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Publishing...';

    try {
        let fileUrl = '';
        let fileName = '';
        let filePublicId = '';

        if (file) {
            const uploaded = await uploadAttachment(file);
            fileName = uploaded.fileName;
            fileUrl = uploaded.fileUrl;
            filePublicId = uploaded.filePublicId;
        }

        await addDoc(collection(db, 'classes', classId, 'posts'), {
            title,
            description,
            authorId: currentUser.uid,
            authorName: currentUserName,
            fileName,
            filePublicId,
            fileUrl,
            createdAt: serverTimestamp()
        });

        await loadPosts();
        closePostModal();
    } catch (error) {
        console.error('Could not publish class post:', error);
        alert(error.message || 'Could not publish this post. Please try again.');
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

addPostButton?.addEventListener('click', openPostModal);
closePostModalButton?.addEventListener('click', closePostModal);
cancelPostModalButton?.addEventListener('click', closePostModal);
modal?.addEventListener('click', event => {
    if (event.target === modal) closePostModal();
});
postFileInput?.addEventListener('change', () => {
    updatePostFilePreview();
});
addPostForm?.addEventListener('submit', submitPost);

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

        const userData = userSnapshot.exists() ? userSnapshot.data() : {};
        currentUserName = userData.name || user.displayName || user.email?.split('@')[0] || 'User';
        setInitial(profileInitial, currentUserName);

        className.textContent = currentClass.className || 'Unnamed Class';
        subjectCode.textContent = `${currentClass.subjectCode || currentClass.classCode || ''} • ${currentClass.yearLevel || ''}`;
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
