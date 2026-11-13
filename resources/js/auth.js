import { auth, db } from './firebase';
import { showAlert } from './alert-modal';

import {
    browserLocalPersistence,
    browserSessionPersistence,
    createUserWithEmailAndPassword,
    onAuthStateChanged,
    sendPasswordResetEmail,
    sendEmailVerification,
    setPersistence,
    signInWithEmailAndPassword,
    signOut,
    updateProfile
} from 'firebase/auth';

import {
    doc,
    serverTimestamp,
    setDoc
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

document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const passwordInput = document.getElementById(button.getAttribute('aria-controls'));
        const icon = button.querySelector('i');
        const isVisible = passwordInput.type === 'text';

        passwordInput.type = isVisible ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!isVisible));
        button.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        icon.classList.toggle('fa-eye', isVisible);
        icon.classList.toggle('fa-eye-slash', !isVisible);
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
            showAlert('Please fill in all fields.');
            return;
        }

        if (password !== confirmPassword) {
            showAlert('Passwords do not match.');
            return;
        }

        const validPassword =
            password.length >= 8 &&
            /[A-Za-z]/.test(password) &&
            /\d/.test(password) &&
            /[^A-Za-z0-9]/.test(password);

        if (!validPassword) {
            showAlert(
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

            showAlert(
                'Account created! We sent a verification link to your email. Please verify your email before logging in.'
            );

        } catch (error) {
            console.error(error);

            if (
                error.code ===
                'auth/email-already-in-use'
            ) {
                showAlert(
                    'This email already has an account.'
                );

            } else if (
                error.code ===
                'auth/invalid-email'
            ) {
                showAlert(
                    'Please enter a valid email address.'
                );

            } else if (
                error.code ===
                'auth/weak-password'
            ) {
                showAlert(
                    'Your password is too weak.'
                );

            } else {
                showAlert(
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
    onAuthStateChanged(auth, (user) => {
        if (user?.emailVerified) {
            window.location.replace('/dashboard');
        }
    });

    const forgotPasswordLink =
        loginForm.querySelector('#forgot-password-link');

    forgotPasswordLink?.addEventListener('click', async (event) => {
        event.preventDefault();

        if (forgotPasswordLink.getAttribute('aria-disabled') === 'true') {
            return;
        }

        const emailInput =
            loginForm.querySelector('[name="email"]');
        const email = emailInput?.value.trim();

        if (!email) {
            showAlert('Enter your email address and then select Forgot Password.');
            emailInput?.focus();
            return;
        }

        forgotPasswordLink.setAttribute('aria-disabled', 'true');
        forgotPasswordLink.textContent = 'Sending...';

        try {
            await sendPasswordResetEmail(auth, email);
            showAlert('If an account exists for that email, a password reset link will be sent.');
        } catch (error) {
            console.error(error);

            if (error.code === 'auth/invalid-email') {
                showAlert('Please enter a valid email address.');
            } else if (error.code === 'auth/too-many-requests') {
                showAlert('Too many requests. Please wait a moment before trying again.');
            } else {
                showAlert('Could not send a password reset email. Please try again.');
            }
        } finally {
            forgotPasswordLink.removeAttribute('aria-disabled');
            forgotPasswordLink.textContent = 'Forgot Password?';
        }
    });

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
                showAlert(
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

                    showAlert(
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

                showAlert(
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
