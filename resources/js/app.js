import './bootstrap';
import '../css/dashboard.css';

import { auth, db } from './firebase';

import {
    browserLocalPersistence,
    browserSessionPersistence,
    createUserWithEmailAndPassword,
    onAuthStateChanged,
    sendEmailVerification,
    setPersistence,
    signInWithEmailAndPassword,
    signOut,
    updateProfile
} from 'firebase/auth';

import {
    addDoc,
    collection,
    deleteDoc,
    doc,
    getDoc,
    getDocs,
    query,
    serverTimestamp,
    setDoc,
    updateDoc,
    writeBatch,
    where
} from 'firebase/firestore';


/* =========================================================
   LOGIN / CREATE ACCOUNT TABS
========================================================= */

const tabs = document.querySelectorAll('.auth-tab');
const forms = document.querySelectorAll('.auth-form');

function showForm(formId) {
    tabs.forEach((tab) => {
        const isActive = tab.dataset.form === formId;

        tab.classList.toggle('active', isActive);
        tab.setAttribute(
            'aria-selected',
            isActive ? 'true' : 'false'
        );
    });

    forms.forEach((form) => {
        form.classList.toggle(
            'active',
            form.id === formId
        );
    });
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        showForm(tab.dataset.form);
    });
});

document.querySelectorAll('[data-switch]').forEach((button) => {
    button.addEventListener('click', () => {
        const formId =
            button.dataset.switch === 'register'
                ? 'register-form'
                : 'login-form';

        showForm(formId);
    });
});


/* =========================================================
   CREATE ACCOUNT
========================================================= */

const registerForm = document.querySelector('#register-form');

if (registerForm) {
    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name =
            registerForm
                .querySelector('[name="name"]')
                ?.value.trim();

        const email =
            registerForm
                .querySelector('[name="register_email"]')
                ?.value.trim();

        const password =
            registerForm
                .querySelector('[name="register_password"]')
                ?.value;

        const confirmPassword =
            registerForm
                .querySelector('[name="password_confirmation"]')
                ?.value;

        if (!name || !email || !password || !confirmPassword) {
            alert('Please fill in all fields.');
            return;
        }

        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            return;
        }

        const validPassword =
            password.length >= 8 &&
            /[A-Za-z]/.test(password) &&
            /\d/.test(password) &&
            /[^A-Za-z0-9]/.test(password);

        if (!validPassword) {
            alert(
                'Password must have at least 8 characters, including a letter, number, and symbol.'
            );
            return;
        }

        const submitButton =
            registerForm.querySelector(
                'button[type="submit"]'
            );

        const originalText =
            submitButton?.textContent;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent =
                'Creating Account...';
        }

        try {
            const userCredential =
                await createUserWithEmailAndPassword(
                    auth,
                    email,
                    password
                );

            const user =
                userCredential.user;

            await updateProfile(user, {
                displayName: name
            });

            await setDoc(
                doc(db, 'users', user.uid),
                {
                    uid: user.uid,
                    name: name,
                    email: email,
                    role: 'teacher',
                    createdAt: serverTimestamp()
                }
            );

            await sendEmailVerification(user);

            await signOut(auth);

            registerForm.reset();

            showForm('login-form');

            alert(
                'Account created! We sent a verification link to your email. Please verify your email before logging in.'
            );

        } catch (error) {
            console.error(error);

            if (
                error.code ===
                'auth/email-already-in-use'
            ) {
                alert(
                    'This email already has an account.'
                );

            } else if (
                error.code ===
                'auth/invalid-email'
            ) {
                alert(
                    'Please enter a valid email address.'
                );

            } else if (
                error.code ===
                'auth/weak-password'
            ) {
                alert(
                    'Your password is too weak.'
                );

            } else {
                alert(
                    'Could not create the account. Please try again.'
                );
            }

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent =
                    originalText;
            }
        }
    });
}


/* =========================================================
   LOG IN
========================================================= */

const loginForm =
    document.querySelector('#login-form');

if (loginForm) {
    loginForm.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            const email =
                loginForm
                    .querySelector('[name="email"]')
                    ?.value.trim();

            const password =
                loginForm
                    .querySelector('[name="password"]')
                    ?.value;

            const remember =
                loginForm.querySelector(
                    '[name="remember"]'
                );

            if (!email || !password) {
                alert(
                    'Please enter your email and password.'
                );
                return;
            }

            const submitButton =
                loginForm.querySelector(
                    'button[type="submit"]'
                );

            const originalText =
                submitButton?.textContent;

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent =
                    'Logging In...';
            }

            try {
                await setPersistence(
                    auth,
                    remember?.checked
                        ? browserLocalPersistence
                        : browserSessionPersistence
                );

                const userCredential =
                    await signInWithEmailAndPassword(
                        auth,
                        email,
                        password
                    );

                const user =
                    userCredential.user;

                if (!user.emailVerified) {
                    await signOut(auth);

                    alert(
                        'Please verify your email first. Check your inbox or spam folder for the verification link.'
                    );

                    if (submitButton) {
                        submitButton.disabled =
                            false;

                        submitButton.textContent =
                            originalText;
                    }

                    return;
                }

                window.location.href =
                    '/dashboard';

            } catch (error) {
                console.error(error);

                alert(
                    'Incorrect email or password.'
                );

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent =
                        originalText;
                }
            }
        }
    );
}


/* =========================================================
   DASHBOARD
========================================================= */

const dashboardPage =
    document.querySelector('.dashboard-page');

if (dashboardPage) {

    const profileButton =
        document.querySelector(
            '#profile-button'
        );

    const profileMenu =
        document.querySelector(
            '#profile-menu'
        );

    const profileName =
        document.querySelector(
            '#profile-name'
        );

    const profileEmail =
        document.querySelector(
            '#profile-email'
        );

    const profileMenuInitial =
        document.querySelector(
            '#profile-menu-initial'
        );

    const profileButtonImage =
        document.querySelector(
            '#profile-button-image'
        );

    const profileMenuImage =
        document.querySelector(
            '#profile-menu-image'
        );

    const profileInitial =
        document.querySelector(
            '#profile-initial'
        );

    const profileModal =
        document.querySelector(
            '#profile-modal'
        );

    const closeProfileModalButton =
        document.querySelector(
            '#close-profile-modal'
        );

    const closeProfileViewButton =
        document.querySelector(
            '#close-profile-view'
        );

    const displayProfileButton =
        document.querySelector(
            '#display-profile-button'
        );

    const editProfileButton =
        document.querySelector(
            '#edit-profile-button'
        );

    const startProfileEditButton =
        document.querySelector(
            '#start-profile-edit'
        );

    const cancelProfileEditButton =
        document.querySelector(
            '#cancel-profile-edit'
        );

    const profileDisplayView =
        document.querySelector(
            '#profile-display-view'
        );

    const profileEditForm =
        document.querySelector(
            '#edit-profile-form'
        );

    const profileModalTitle =
        document.querySelector(
            '#profile-modal-title'
        );

    const profileModalSubtitle =
        document.querySelector(
            '#profile-modal-subtitle'
        );

    const profileModalInitial =
        document.querySelector(
            '#profile-modal-initial'
        );

    const profileModalImage =
        document.querySelector(
            '#profile-modal-image'
        );

    const profileEditImageInput =
        document.querySelector(
            '#profile-image-input'
        );

    const profileEditImagePreview =
        document.querySelector(
            '#profile-edit-image-preview'
        );

    const profileEditInitial =
        document.querySelector(
            '#profile-edit-initial'
        );

    const profileDetailName =
        document.querySelector(
            '#profile-detail-name'
        );

    const profileDetailEmail =
        document.querySelector(
            '#profile-detail-email'
        );

    const profileEditName =
        document.querySelector(
            '#profile-edit-name'
        );

    const profileEditEmail =
        document.querySelector(
            '#profile-edit-email'
        );

    const saveProfileButton =
        document.querySelector(
            '#save-profile-button'
        );

    const logoutButton =
        document.querySelector(
            '#logout-button'
        );

    const classModal =
        document.querySelector(
            '#class-modal'
        );

    const openClassModalButton =
        document.querySelector(
            '#open-class-modal'
        );

    const emptyAddClassButton =
        document.querySelector(
            '#empty-add-class'
        );

    const closeClassModalButton =
        document.querySelector(
            '#close-class-modal'
        );

    const cancelClassModalButton =
        document.querySelector(
            '#cancel-class-modal'
        );

    const addClassForm =
        document.querySelector(
            '#add-class-form'
        );

    const generatedCodeInput =
        document.querySelector(
            '#generated-class-code'
        );

    const regenerateCodeButton =
        document.querySelector(
            '#regenerate-code'
        );

    const classesGrid =
        document.querySelector(
            '#classes-grid'
        );

    const classesLoading =
        document.querySelector(
            '#classes-loading'
        );

    const emptyClasses =
        document.querySelector(
            '#empty-classes'
        );

    const classSearchInput =
        document.querySelector('#class-search');

    const yearLevelFilter =
        document.querySelector('#year-level-filter');

    const classResultsCount =
        document.querySelector('#class-results-count');

    const noClassResults =
        document.querySelector('#no-class-results');

    const gridViewButton =
        document.querySelector('#grid-view-button');

    const listViewButton =
        document.querySelector('#list-view-button');

    const classNameInput =
        document.querySelector(
            '#class-name'
        );

    const descriptionInput =
        document.querySelector(
            '#class-description'
        );

    const classNameCount =
        document.querySelector(
            '#class-name-count'
        );

    const descriptionCount =
        document.querySelector(
            '#description-count'
        );


    /* =====================================================
       CURRENT TEACHER
    ===================================================== */

    let currentTeacher = null;
    let currentProfileImageUrl = '';
    let selectedProfileImagePreviewUrl = '';
    let classLayout = 'grid';

    // Stores the teacher name loaded from:
    // users/{teacherUid}
    let currentTeacherName = 'Teacher';

    function setProfileName(name) {
        currentTeacherName = name;
        const initial = name.charAt(0).toUpperCase() || 'T';

        profileName.textContent = name;
        profileDetailName.textContent = name;
        profileInitial.textContent = initial;
        profileMenuInitial.textContent = initial;
        profileModalInitial.textContent = initial;
        profileEditInitial.textContent = initial;
    }

    function setProfileImage(url) {
        currentProfileImageUrl = url || '';

        [
            profileButtonImage,
            profileMenuImage,
            profileModalImage
        ].forEach((image) => {
            if (currentProfileImageUrl) {
                image.src = currentProfileImageUrl;
            } else {
                image.removeAttribute('src');
            }

            image.hidden = !currentProfileImageUrl;
        });

        profileInitial.hidden = Boolean(currentProfileImageUrl);
        profileMenuInitial.hidden = Boolean(currentProfileImageUrl);
        profileModalInitial.hidden = Boolean(currentProfileImageUrl);
    }

    function setProfileEditPreview(url) {
        if (url) {
            profileEditImagePreview.src = url;
        } else {
            profileEditImagePreview.removeAttribute('src');
        }

        profileEditImagePreview.hidden = !url;
        profileEditInitial.hidden = Boolean(url);
    }

    function clearSelectedProfileImage() {
        if (selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(selectedProfileImagePreviewUrl);
            selectedProfileImagePreviewUrl = '';
        }

        profileEditImageInput.value = '';
        setProfileEditPreview(currentProfileImageUrl);
    }

    async function uploadProfileImage(file) {
        const idToken = await currentTeacher.getIdToken();
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        if (!csrfToken) {
            throw new Error('Could not verify the upload request. Refresh the page and try again.');
        }

        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch('/profile/image', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${idToken}`,
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message || 'Could not upload your profile image.'
            );
        }

        if (typeof result.profileImageUrl !== 'string' || !result.profileImageUrl) {
            throw new Error('The image upload returned an invalid response.');
        }

        return result.profileImageUrl;
    }

    function setProfileModalMode(mode) {
        const isEditing = mode === 'edit';

        profileDisplayView.hidden = isEditing;
        profileEditForm.hidden = !isEditing;
        profileModalTitle.textContent = isEditing
            ? 'Edit Profile'
            : 'Your Profile';
        profileModalSubtitle.textContent = isEditing
            ? 'Update the name shown on your account.'
            : 'Your account information';

        if (isEditing) {
            profileEditName.value = currentTeacherName;
            profileEditEmail.value = currentTeacher?.email || '';
            clearSelectedProfileImage();
            profileEditName.focus();
        }
    }

    function openProfileModal(mode = 'display') {
        profileMenu.hidden = true;
        profileButton.setAttribute('aria-expanded', 'false');
        profileDetailEmail.textContent = currentTeacher?.email || '';
        profileModal.hidden = false;
        document.body.style.overflow = 'hidden';
        setProfileModalMode(mode);

        if (mode === 'display') {
            closeProfileViewButton.focus();
        }
    }

    function closeProfileModal() {
        profileModal.hidden = true;
        document.body.style.overflow = '';
        profileEditForm.reset();
        if (selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(selectedProfileImagePreviewUrl);
            selectedProfileImagePreviewUrl = '';
        }

        profileEditImageInput.value = '';
        setProfileModalMode('display');
    }


    /* =====================================================
       GENERATE CLASS CODE
    ===================================================== */

    function generateClassCode() {
        const characters =
            'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        let code = '';

        for (let i = 0; i < 6; i += 1) {
            code += characters.charAt(
                Math.floor(
                    Math.random() *
                    characters.length
                )
            );
        }

        return code;
    }


    /* =====================================================
       OPEN CLASS MODAL
    ===================================================== */

    function openClassModal() {
        generatedCodeInput.value =
            generateClassCode();

        classModal.hidden = false;

        document.body.style.overflow =
            'hidden';

        classNameInput.focus();
    }


    /* =====================================================
       CLOSE CLASS MODAL
    ===================================================== */

    function closeClassModal() {
        classModal.hidden = true;

        document.body.style.overflow = '';

        addClassForm.reset();

        classNameCount.textContent =
            '0 / 100';

        descriptionCount.textContent =
            '0 / 200';
    }


    /* =====================================================
       ESCAPE HTML
    ===================================================== */

    function escapeHtml(value = '') {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function deleteClassSubcollection(
        classId,
        subcollectionName
    ) {
        const subcollectionSnapshot =
            await getDocs(
                collection(
                    db,
                    'classes',
                    classId,
                    subcollectionName
                )
            );

        for (
            let start = 0;
            start < subcollectionSnapshot.docs.length;
            start += 500
        ) {
            const batch = writeBatch(db);
            const documents = subcollectionSnapshot.docs.slice(
                start,
                start + 500
            );

            documents.forEach((documentSnapshot) => {
                batch.delete(documentSnapshot.ref);
            });

            await batch.commit();
        }
    }

    async function deleteClass(classId) {
        await deleteClassSubcollection(classId, 'posts');
        await deleteClassSubcollection(classId, 'students');
        await deleteDoc(doc(db, 'classes', classId));
    }


    /* =====================================================
       RENDER CLASS CARD
    ===================================================== */

    function renderClassCard(
        classData,
        documentId
    ) {
        const card =
            document.createElement('article');

        card.className =
            'class-card';

        card.dataset.classId =
            documentId;
        card.dataset.yearLevel =
            classData.yearLevel || '';

        const name =
            escapeHtml(
                classData.className ||
                'Untitled Class'
            );

        const subjectCode =
            escapeHtml(
                classData.subjectCode || ''
            );

        const yearLevel =
            escapeHtml(
                classData.yearLevel || ''
            );

        const description =
            escapeHtml(
                classData.description ||
                'No class description.'
            );

        const classCode =
            escapeHtml(
                classData.classCode ||
                '------'
            );

        card.innerHTML = `
            <div class="class-card-top">

                <div class="class-card-icon" aria-hidden="true">
                    <i class="fa-solid fa-book-open"></i>
                </div>

                <div class="class-card-actions">
                    <button
                        type="button"
                        class="class-card-menu"
                        aria-label="Options for ${name}"
                        aria-expanded="false"
                    >
                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="class-card-menu-popover" hidden>
                        <button
                            type="button"
                            class="delete-class-button"
                        >
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            Delete class
                        </button>
                    </div>
                </div>

                <h2>${name}</h2>

                <p class="class-card-subtitle">
                    ${subjectCode} · ${yearLevel}
                </p>

                <p class="class-card-description">
                    ${description}
                </p>

            </div>

            <div class="class-card-body">

                <div class="class-card-info">

                    <div>

                        <span class="class-code-label">
                            <i class="fa-solid fa-link" aria-hidden="true"></i>
                            Class Code
                        </span>

                        <div
                            class="class-code-row"
                        >

                            <strong
                                class="class-code"
                            >
                                ${classCode}
                            </strong>

                            <button
                                type="button"
                                class="copy-code-button"
                                data-code="${classCode}"
                                title="Copy class code"
                            >
                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            </button>

                        </div>

                    </div>

                    <div class="class-stat">
                        <span
                            class="stat-label"
                        >
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            Students
                        </span>

                        <strong>
                            ${classData.studentCount || 0}
                        </strong>
                    </div>

                    <div class="class-stat">
                        <span
                            class="stat-label"
                        >
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            Posts
                        </span>

                        <strong>
                            ${classData.postCount || 0}
                        </strong>
                    </div>

                </div>

                <button
                    type="button"
                    class="view-class-button"
                >
                    View Class
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>

            </div>
        `;

        const menuButton =
            card.querySelector('.class-card-menu');

        const menuPopover =
            card.querySelector('.class-card-menu-popover');

        menuButton.addEventListener('click', () => {
            const isExpanded =
                menuButton.getAttribute('aria-expanded') === 'true';

            document.querySelectorAll('.class-card-menu-popover').forEach((menu) => {
                menu.hidden = true;
                menu.closest('.class-card')
                    ?.querySelector('.class-card-menu')
                    ?.setAttribute('aria-expanded', 'false');
            });

            menuPopover.hidden = isExpanded;
            menuButton.setAttribute('aria-expanded', String(!isExpanded));
        });

        card
            .querySelector('.delete-class-button')
            .addEventListener('click', async (event) => {
                const classTitle =
                    card.querySelector('.class-card-top h2').textContent;

                if (
                    !window.confirm(
                        `Delete "${classTitle}"? This will permanently remove the class, its posts, and its student list.`
                    )
                ) {
                    menuPopover.hidden = true;
                    menuButton.setAttribute('aria-expanded', 'false');
                    return;
                }

                const deleteButton = event.currentTarget;
                deleteButton.disabled = true;
                deleteButton.textContent = 'Deleting...';

                try {
                    await deleteClass(documentId);
                    await loadClasses();
                } catch (error) {
                    console.error(error);
                    deleteButton.disabled = false;
                    deleteButton.innerHTML =
                        '<i class="fa-solid fa-trash" aria-hidden="true"></i> Delete class';
                    alert(
                        'Could not delete the class and its related records. Please try again.'
                    );
                }
            });


        /* =============================================
           COPY CLASS CODE
        ============================================= */

        card
            .querySelector(
                '.copy-code-button'
            )
            .addEventListener(
                'click',
                async (event) => {

                    const copyButton = event.currentTarget;
                    const code =
                        copyButton
                            .dataset.code;

                    try {
                        await navigator
                            .clipboard
                            .writeText(code);

                        copyButton.innerHTML =
                            '<i class="fa-solid fa-check" aria-hidden="true"></i>';

                        window.setTimeout(
                            () => {
                                copyButton.innerHTML =
                                    '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
                            },
                            1200
                        );

                    } catch {
                        alert(
                            `Class code: ${code}`
                        );
                    }
                }
            );


        /* =============================================
           VIEW CLASS
        ============================================= */

        card
            .querySelector('.view-class-button')
            .addEventListener('click', () => {
                window.location.href =
                    `/class/${documentId}`;
            });

        return card;
    }

    function filterClasses() {
        const searchText = classSearchInput.value.trim().toLowerCase();
        const selectedYearLevel = yearLevelFilter.value;
        const cards = Array.from(classesGrid.querySelectorAll('.class-card'));
        let visibleCount = 0;

        cards.forEach((card) => {
            const matchesSearch = card.textContent.toLowerCase().includes(searchText);
            const matchesYearLevel = !selectedYearLevel || card.dataset.yearLevel === selectedYearLevel;
            const isVisible = matchesSearch && matchesYearLevel;

            card.hidden = !isVisible;
            if (isVisible) {
                visibleCount += 1;
            }
        });

        classResultsCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'class' : 'classes'}`;
        noClassResults.hidden = cards.length === 0 || visibleCount > 0;
    }


    function setClassLayout(layout) {
        classLayout = layout;
        classesGrid.classList.toggle('is-list-view', layout === 'list');
        gridViewButton.classList.toggle('is-active', layout === 'grid');
        listViewButton.classList.toggle('is-active', layout === 'list');
        gridViewButton.setAttribute('aria-pressed', String(layout === 'grid'));
        listViewButton.setAttribute('aria-pressed', String(layout === 'list'));
    }


    classSearchInput.addEventListener('input', filterClasses);
    yearLevelFilter.addEventListener('change', filterClasses);
    gridViewButton.addEventListener('click', () => setClassLayout('grid'));
    listViewButton.addEventListener('click', () => setClassLayout('list'));


    /* =====================================================
       LOAD TEACHER CLASSES
    ===================================================== */

    async function loadClasses() {

        classesLoading.hidden = false;
        emptyClasses.hidden = true;

        try {

            const classesQuery =
                query(
                    collection(
                        db,
                        'classes'
                    ),
                    where(
                        'teacherId',
                        '==',
                        currentTeacher.uid
                    )
                );

            const snapshot =
                await getDocs(
                    classesQuery
                );

            const classDocuments = await Promise.all(
                snapshot.docs.map(async (classDocument) => {
                    const [studentsSnapshot, postsSnapshot] =
                        await Promise.all([
                            getDocs(
                                collection(
                                    db,
                                    'classes',
                                    classDocument.id,
                                    'students'
                                )
                            ),
                            getDocs(
                                collection(
                                    db,
                                    'classes',
                                    classDocument.id,
                                    'posts'
                                )
                            )
                        ]);

                    return {
                        id: classDocument.id,
                        ...classDocument.data(),
                        studentCount: studentsSnapshot.size,
                        postCount: postsSnapshot.size
                    };
                })
            );

            classDocuments.sort(
                (a, b) => {

                    const first =
                        a.createdAt
                            ?.toMillis?.() || 0;

                    const second =
                        b.createdAt
                            ?.toMillis?.() || 0;

                    return second - first;
                }
            );

            classesGrid.innerHTML = '';
            classesLoading.hidden = true;

            if (
                classDocuments.length === 0
            ) {
                emptyClasses.hidden = false;
                classResultsCount.textContent = '0 classes';
                noClassResults.hidden = true;
                return;
            }

            emptyClasses.hidden = true;
            classDocuments.forEach(
                (classData) => {

                    classesGrid.appendChild(
                        renderClassCard(
                            classData,
                            classData.id
                        )
                    );
                }
            );
            filterClasses();
            setClassLayout(classLayout);

        } catch (error) {

            console.error(error);
            classesLoading.hidden = true;

            classesGrid.innerHTML = `
                <p class="classes-loading">
                    Could not load classes.
                    Check your Firestore rules.
                </p>
            `;
        }
    }


    /* =====================================================
       AUTHENTICATION STATE
    ===================================================== */

    onAuthStateChanged(
        auth,
        async (user) => {

            if (!user) {
                window.location.replace('/');
                return;
            }

            if (!user.emailVerified) {

                await signOut(auth);

                alert(
                    'Please verify your email before accessing the dashboard.'
                );

                window.location.replace('/');

                return;
            }


            /* =============================================
               SAVE CURRENT TEACHER
            ============================================= */

            currentTeacher = user;


            /* =============================================
               GET TEACHER NAME
            ============================================= */

            let teacherName =
                user.displayName ||
                'Teacher';

            try {

                const userDocument =
                    await getDoc(
                        doc(
                            db,
                            'users',
                            user.uid
                        )
                    );

                if (
                    userDocument.exists()
                ) {
                    const userData = userDocument.data();
                    teacherName =
                        userData.name ||
                        teacherName;

                    setProfileImage(
                        userData.profileImageUrl || user.photoURL || ''
                    );
                } else {
                    setProfileImage(user.photoURL || '');
                }

            } catch (error) {
                console.error(error);
                setProfileImage(user.photoURL || '');
            }


            /* =============================================
               UPDATE PROFILE UI
            ============================================= */

            profileEmail.textContent =
                user.email || '';

            profileDetailEmail.textContent =
                user.email || '';

            profileEditEmail.value =
                user.email || '';

            setProfileName(teacherName);


            /* =============================================
               LOAD CLASSES
            ============================================= */

            await loadClasses();
        }
    );


    /* =====================================================
       PROFILE MENU
    ===================================================== */

    profileButton.addEventListener(
        'click',
        () => {

            const isHidden =
                profileMenu.hidden;

            profileMenu.hidden =
                !isHidden;

            profileButton.setAttribute(
                'aria-expanded',
                String(isHidden)
            );
        }
    );

    displayProfileButton.addEventListener(
        'click',
        () => openProfileModal('display')
    );

    editProfileButton.addEventListener(
        'click',
        () => openProfileModal('edit')
    );

    startProfileEditButton.addEventListener(
        'click',
        () => setProfileModalMode('edit')
    );

    closeProfileModalButton.addEventListener(
        'click',
        closeProfileModal
    );

    closeProfileViewButton.addEventListener(
        'click',
        closeProfileModal
    );

    cancelProfileEditButton.addEventListener(
        'click',
        () => {
            clearSelectedProfileImage();
            setProfileModalMode('display');
        }
    );

    profileEditImageInput.addEventListener('change', () => {
        const file = profileEditImageInput.files[0];
        if (!file) {
            clearSelectedProfileImage();
            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
            clearSelectedProfileImage();
            alert('Choose a JPG, PNG, or WebP image smaller than 5 MB.');
            return;
        }

        if (selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(selectedProfileImagePreviewUrl);
        }

        selectedProfileImagePreviewUrl = URL.createObjectURL(file);
        setProfileEditPreview(selectedProfileImagePreviewUrl);
    });

    profileModal.addEventListener('click', (event) => {
        if (event.target === profileModal) {
            closeProfileModal();
        }
    });

    profileEditForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = profileEditName.value.trim();
        if (!name || !currentTeacher) {
            profileEditName.focus();
            return;
        }

        saveProfileButton.disabled = true;
        saveProfileButton.textContent = 'Saving...';

        let updatedProfileImageUrl = currentProfileImageUrl;
        let firestoreProfileSaved = false;

        try {
            const selectedImage = profileEditImageInput.files[0];
            if (selectedImage) {
                saveProfileButton.textContent = 'Uploading photo...';
                updatedProfileImageUrl =
                    await uploadProfileImage(selectedImage);
            }

            saveProfileButton.textContent = 'Saving...';
            await updateDoc(
                doc(db, 'users', currentTeacher.uid),
                {
                    name,
                    profileImageUrl: updatedProfileImageUrl
                }
            );
            firestoreProfileSaved = true;

            await updateProfile(currentTeacher, {
                displayName: name,
                photoURL: updatedProfileImageUrl || null
            });

            setProfileName(name);
            setProfileImage(updatedProfileImageUrl);
            profileDetailEmail.textContent =
                currentTeacher.email || '';
            profileEmail.textContent =
                currentTeacher.email || '';
            closeProfileModal();
        } catch (error) {
            console.error(error);
            if (firestoreProfileSaved) {
                setProfileName(name);
                setProfileImage(updatedProfileImageUrl);
                closeProfileModal();
                alert('Your profile was saved, but could not be synchronized with your sign-in profile.');
            } else {
                alert(error.message || 'Could not update your profile. Please try again.');
            }
        } finally {
            saveProfileButton.disabled = false;
            saveProfileButton.textContent = 'Save changes';
        }
    });


    document.addEventListener(
        'click',
        (event) => {

            if (
                !profileMenu.hidden &&
                !profileMenu.contains(
                    event.target
                ) &&
                !profileButton.contains(
                    event.target
                )
            ) {
                profileMenu.hidden = true;

                profileButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        }
    );

    document.addEventListener('click', (event) => {
        if (
            event.target instanceof Element &&
            event.target.closest('.class-card-actions')
        ) {
            return;
        }

        document.querySelectorAll('.class-card-menu-popover').forEach((menu) => {
            menu.hidden = true;
            menu.closest('.class-card')
                ?.querySelector('.class-card-menu')
                ?.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (!profileModal.hidden) {
            closeProfileModal();
        }

        document.querySelectorAll('.class-card-menu-popover').forEach((menu) => {
            menu.hidden = true;
            menu.closest('.class-card')
                ?.querySelector('.class-card-menu')
                ?.setAttribute('aria-expanded', 'false');
        });
    });


    /* =====================================================
       LOG OUT
    ===================================================== */

    logoutButton.addEventListener(
        'click',
        async () => {

            await signOut(auth);

            window.location.replace('/');
        }
    );


    /* =====================================================
       CLASS MODAL EVENTS
    ===================================================== */

    openClassModalButton.addEventListener(
        'click',
        openClassModal
    );

    emptyAddClassButton.addEventListener(
        'click',
        openClassModal
    );

    closeClassModalButton.addEventListener(
        'click',
        closeClassModal
    );

    cancelClassModalButton.addEventListener(
        'click',
        closeClassModal
    );

    regenerateCodeButton.addEventListener(
        'click',
        () => {
            generatedCodeInput.value =
                generateClassCode();
        }
    );


    classModal.addEventListener(
        'click',
        (event) => {

            if (
                event.target === classModal
            ) {
                closeClassModal();
            }
        }
    );


    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape' &&
                !classModal.hidden
            ) {
                closeClassModal();
            }
        }
    );


    /* =====================================================
       CHARACTER COUNTERS
    ===================================================== */

    classNameInput.addEventListener(
        'input',
        () => {

            classNameCount.textContent =
                `${classNameInput.value.length} / 100`;
        }
    );


    descriptionInput.addEventListener(
        'input',
        () => {

            descriptionCount.textContent =
                `${descriptionInput.value.length} / 200`;
        }
    );


    /* =====================================================
       CREATE CLASS
    ===================================================== */

    addClassForm.addEventListener(
        'submit',
        async (event) => {

            event.preventDefault();

            if (!currentTeacher) {
                return;
            }

            const formData =
                new FormData(
                    addClassForm
                );

            const className =
                formData
                    .get('class_name')
                    ?.trim();

            const subjectCode =
                formData
                    .get('subject_code')
                    ?.trim();

            const yearLevel =
                formData.get(
                    'year_level'
                );

            const description =
                formData
                    .get('description')
                    ?.trim();

            const classCode =
                generatedCodeInput.value;

            if (
                !className ||
                !subjectCode ||
                !yearLevel
            ) {
                alert(
                    'Please complete all required fields.'
                );

                return;
            }

            const createButton =
                addClassForm.querySelector(
                    '.create-class-button'
                );

            const originalText =
                createButton.textContent;

            createButton.disabled = true;

            createButton.textContent =
                'Creating...';

            try {

                /* =========================================
                   CREATE CLASS DOCUMENT
                ========================================= */

                await addDoc(
                    collection(
                        db,
                        'classes'
                    ),
                    {
                        teacherId:
                            currentTeacher.uid,

                        // Teacher name copied from users/{uid}
                        teacherName:
                            currentTeacherName,

                        className:
                            className,

                        subjectCode:
                            subjectCode,

                        yearLevel:
                            yearLevel,

                        description:
                            description,

                        classCode:
                            classCode,

                        createdAt:
                            serverTimestamp()
                    }
                );


                /* =========================================
                   REFRESH DASHBOARD
                ========================================= */

                closeClassModal();

                await loadClasses();

            } catch (error) {

                console.error(error);

                alert(
                    'Could not create the class. Check your Firestore rules.'
                );

            } finally {

                createButton.disabled =
                    false;

                createButton.textContent =
                    originalText;
            }
        }
    );
}