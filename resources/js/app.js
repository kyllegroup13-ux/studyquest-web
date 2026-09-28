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
    doc,
    getDoc,
    getDocs,
    query,
    serverTimestamp,
    setDoc,
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
            registerForm.querySelector('[name="name"]')?.value.trim();

        const email =
            registerForm.querySelector('[name="register_email"]')?.value.trim();

        const password =
            registerForm.querySelector('[name="register_password"]')?.value;

        const confirmPassword =
            registerForm.querySelector(
                '[name="password_confirmation"]'
            )?.value;

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
            registerForm.querySelector('button[type="submit"]');

        const originalText = submitButton?.textContent;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Creating Account...';
        }

        try {
            const userCredential =
                await createUserWithEmailAndPassword(
                    auth,
                    email,
                    password
                );

            const user = userCredential.user;

            await updateProfile(user, {
                displayName: name
            });

            await setDoc(doc(db, 'users', user.uid), {
                name,
                email,
                role: 'teacher',
                createdAt: serverTimestamp()
            });

            await sendEmailVerification(user);

            await signOut(auth);

            registerForm.reset();
            showForm('login-form');

            alert(
                'Account created! We sent a verification link to your email. Please verify your email before logging in.'
            );
        } catch (error) {
            console.error(error);

            if (error.code === 'auth/email-already-in-use') {
                alert('This email already has an account.');
            } else if (error.code === 'auth/invalid-email') {
                alert('Please enter a valid email address.');
            } else if (error.code === 'auth/weak-password') {
                alert('Your password is too weak.');
            } else {
                alert('Could not create the account. Please try again.');
            }

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    });
}


/* =========================================================
   LOG IN
========================================================= */

const loginForm = document.querySelector('#login-form');

if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const email =
            loginForm.querySelector('[name="email"]')?.value.trim();

        const password =
            loginForm.querySelector('[name="password"]')?.value;

        const remember =
            loginForm.querySelector('[name="remember"]');

        if (!email || !password) {
            alert('Please enter your email and password.');
            return;
        }

        const submitButton =
            loginForm.querySelector('button[type="submit"]');

        const originalText = submitButton?.textContent;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Logging In...';
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

            const user = userCredential.user;

            if (!user.emailVerified) {
                await signOut(auth);

                alert(
                    'Please verify your email first. Check your inbox or spam folder for the verification link.'
                );

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }

                return;
            }

            window.location.href = '/dashboard';
        } catch (error) {
            console.error(error);
            alert('Incorrect email or password.');

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    });
}


/* =========================================================
   DASHBOARD
========================================================= */

const dashboardPage = document.querySelector('.dashboard-page');

if (dashboardPage) {
    const profileButton =
        document.querySelector('#profile-button');

    const profileMenu =
        document.querySelector('#profile-menu');

    const profileName =
        document.querySelector('#profile-name');

    const profileEmail =
        document.querySelector('#profile-email');

    const profileInitial =
        document.querySelector('#profile-initial');

    const logoutButton =
        document.querySelector('#logout-button');

    const classModal =
        document.querySelector('#class-modal');

    const openClassModalButton =
        document.querySelector('#open-class-modal');

    const emptyAddClassButton =
        document.querySelector('#empty-add-class');

    const closeClassModalButton =
        document.querySelector('#close-class-modal');

    const cancelClassModalButton =
        document.querySelector('#cancel-class-modal');

    const addClassForm =
        document.querySelector('#add-class-form');

    const generatedCodeInput =
        document.querySelector('#generated-class-code');

    const regenerateCodeButton =
        document.querySelector('#regenerate-code');

    const classesGrid =
        document.querySelector('#classes-grid');

    const classesLoading =
        document.querySelector('#classes-loading');

    const emptyClasses =
        document.querySelector('#empty-classes');

    const classNameInput =
        document.querySelector('#class-name');

    const descriptionInput =
        document.querySelector('#class-description');

    const classNameCount =
        document.querySelector('#class-name-count');

    const descriptionCount =
        document.querySelector('#description-count');

    let currentTeacher = null;


    function generateClassCode() {
        const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = '';

        for (let i = 0; i < 6; i += 1) {
            code += characters.charAt(
                Math.floor(Math.random() * characters.length)
            );
        }

        return code;
    }


    function openClassModal() {
        generatedCodeInput.value = generateClassCode();
        classModal.hidden = false;
        document.body.style.overflow = 'hidden';
        classNameInput.focus();
    }


    function closeClassModal() {
        classModal.hidden = true;
        document.body.style.overflow = '';
        addClassForm.reset();
        classNameCount.textContent = '0 / 100';
        descriptionCount.textContent = '0 / 200';
    }


    function escapeHtml(value = '') {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }


    function renderClassCard(classData, documentId) {
        const card = document.createElement('article');
        card.className = 'class-card';
        card.dataset.classId = documentId;

        const name = escapeHtml(classData.className || 'Untitled Class');
        const subjectCode = escapeHtml(classData.subjectCode || '');
        const yearLevel = escapeHtml(classData.yearLevel || '');
        const description = escapeHtml(
            classData.description || 'No class description.'
        );
        const classCode = escapeHtml(classData.classCode || '------');

        card.innerHTML = `
            <div class="class-card-top">
                <div class="class-card-icon"></div>

                <button
                    type="button"
                    class="class-card-menu"
                    aria-label="Class options"
                >
                    ⋮
                </button>

                <h2>${name}</h2>
                <p>${subjectCode} · ${yearLevel}</p>
                <p>${description}</p>
            </div>

            <div class="class-card-body">
                <div class="class-card-info">
                    <div>
                        <span class="class-code-label">Class Code</span>

                        <div class="class-code-row">
                            <strong class="class-code">${classCode}</strong>

                            <button
                                type="button"
                                class="copy-code-button"
                                data-code="${classCode}"
                                title="Copy class code"
                            >
                                ⧉
                            </button>
                        </div>
                    </div>

                    <div class="class-stat">
                        <span class="stat-label">Students</span>
                        <strong>${classData.studentCount || 0}</strong>
                    </div>

                    <div class="class-stat">
                        <span class="stat-label">Materials</span>
                        <strong>${classData.materialCount || 0}</strong>
                    </div>
                </div>

                <button type="button" class="view-class-button">
                    View Class →
                </button>
            </div>
        `;

        card.querySelector('.copy-code-button')
            .addEventListener('click', async (event) => {
                const code = event.currentTarget.dataset.code;

                try {
                    await navigator.clipboard.writeText(code);
                    event.currentTarget.textContent = '✓';

                    window.setTimeout(() => {
                        event.currentTarget.textContent = '⧉';
                    }, 1200);
                } catch {
                    alert(`Class code: ${code}`);
                }
            });

        card.querySelector('.view-class-button')
            .addEventListener('click', () => {
                alert('The class page will be connected next.');
            });

        return card;
    }


    async function loadClasses() {
        classesLoading.hidden = false;
        emptyClasses.hidden = true;

        try {
            const classesQuery = query(
                collection(db, 'classes'),
                where('teacherId', '==', currentTeacher.uid)
            );

            const snapshot = await getDocs(classesQuery);

            const classDocuments = [];

            snapshot.forEach((classDocument) => {
                classDocuments.push({
                    id: classDocument.id,
                    ...classDocument.data()
                });
            });

            classDocuments.sort((a, b) => {
                const first = a.createdAt?.toMillis?.() || 0;
                const second = b.createdAt?.toMillis?.() || 0;

                return second - first;
            });

            classesGrid.innerHTML = '';

            if (classDocuments.length === 0) {
                emptyClasses.hidden = false;
                return;
            }

            classDocuments.forEach((classData) => {
                classesGrid.appendChild(
                    renderClassCard(classData, classData.id)
                );
            });
        } catch (error) {
            console.error(error);
            classesGrid.innerHTML = `
                <p class="classes-loading">
                    Could not load classes. Check your Firestore rules.
                </p>
            `;
        }
    }


    onAuthStateChanged(auth, async (user) => {
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

        currentTeacher = user;

        let teacherName =
            user.displayName || 'Teacher';

        try {
            const userDocument =
                await getDoc(doc(db, 'users', user.uid));

            if (userDocument.exists()) {
                teacherName =
                    userDocument.data().name || teacherName;
            }
        } catch (error) {
            console.error(error);
        }

        profileName.textContent = teacherName;
        profileEmail.textContent = user.email || '';
        profileInitial.textContent =
            teacherName.charAt(0).toUpperCase();

        await loadClasses();
    });


    profileButton.addEventListener('click', () => {
        const isHidden = profileMenu.hidden;

        profileMenu.hidden = !isHidden;
        profileButton.setAttribute(
            'aria-expanded',
            String(isHidden)
        );
    });


    document.addEventListener('click', (event) => {
        if (
            !profileMenu.hidden &&
            !profileMenu.contains(event.target) &&
            !profileButton.contains(event.target)
        ) {
            profileMenu.hidden = true;
            profileButton.setAttribute(
                'aria-expanded',
                'false'
            );
        }
    });


    logoutButton.addEventListener('click', async () => {
        await signOut(auth);
        window.location.replace('/');
    });


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

    regenerateCodeButton.addEventListener('click', () => {
        generatedCodeInput.value = generateClassCode();
    });


    classModal.addEventListener('click', (event) => {
        if (event.target === classModal) {
            closeClassModal();
        }
    });


    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            !classModal.hidden
        ) {
            closeClassModal();
        }
    });


    classNameInput.addEventListener('input', () => {
        classNameCount.textContent =
            `${classNameInput.value.length} / 100`;
    });


    descriptionInput.addEventListener('input', () => {
        descriptionCount.textContent =
            `${descriptionInput.value.length} / 200`;
    });


    addClassForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!currentTeacher) {
            return;
        }

        const formData = new FormData(addClassForm);

        const className =
            formData.get('class_name')?.trim();

        const subjectCode =
            formData.get('subject_code')?.trim();

        const yearLevel =
            formData.get('year_level');

        const description =
            formData.get('description')?.trim();

        const classCode =
            generatedCodeInput.value;

        if (!className || !subjectCode || !yearLevel) {
            alert('Please complete all required fields.');
            return;
        }

        const createButton =
            addClassForm.querySelector(
                '.create-class-button'
            );

        const originalText =
            createButton.textContent;

        createButton.disabled = true;
        createButton.textContent = 'Creating...';

        try {
            await addDoc(collection(db, 'classes'), {
                teacherId: currentTeacher.uid,
                className,
                subjectCode,
                yearLevel,
                description,
                classCode,
                studentCount: 0,
                materialCount: 0,
                createdAt: serverTimestamp()
            });

            closeClassModal();
            await loadClasses();
        } catch (error) {
            console.error(error);
            alert(
                'Could not create the class. Check your Firestore rules.'
            );
        } finally {
            createButton.disabled = false;
            createButton.textContent = originalText;
        }
    });
}
