<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyQuest - Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-page">
        <div class="auth-container">
            <section class="intro-section">
                <div class="brand">
                    <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="StudyQuest logo" class="brand-logo">
                    <span class="brand-name">StudyQuest</span>
                </div>

                <div class="intro-copy">
                    <h1>Empower Your<br>Classrooms</h1>
                    <p>
                        Share learning materials, manage<br class="desktop-break">
                        students digitally, and enhance their<br class="desktop-break">
                        learning through AI.
                    </p>
                </div>

                <div class="features">
                    <div class="feature-item">
                        <img src="{{ Vite::asset('resources/images/upload-icon.png') }}" alt="" class="feature-icon">
                        <div>
                            <h2>Upload Materials</h2>
                            <p>Share resources with ease.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <img src="{{ Vite::asset('resources/images/manage-icon.png') }}" alt="" class="feature-icon">
                        <div>
                            <h2>Manage Your Classes</h2>
                            <p>Connect with your students.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <img src="{{ Vite::asset('resources/images/monitor-icon.png') }}" alt="" class="feature-icon">
                        <div>
                            <h2>Monitor Progress</h2>
                            <p>See how your students are<br>learning.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="auth-card">
                    <div class="card-heading">
                        <h2>Welcome, Teacher!</h2>
                        <p>Log in to your StudyQuest account and continue monitoring<br>your class.</p>
                    </div>

                    <div class="auth-tabs" role="tablist">
                        <button class="auth-tab active" id="login-tab" type="button" role="tab" aria-selected="true" data-form="login-form">
                            Log In
                        </button>
                        <button class="auth-tab" id="register-tab" type="button" role="tab" aria-selected="false" data-form="register-form">
                            Create Account
                        </button>
                    </div>

                    <form class="auth-form active" id="login-form" action="#" method="POST">
                        <div class="input-box">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <rect x="3" y="5" width="18" height="14" rx="1.8"></rect>
                                    <path d="m4 7 8 6 8-6"></path>
                                </svg>
                            </span>
                            <input type="email" name="email" placeholder="Email Address" autocomplete="email">
                        </div>

                        <div class="input-box">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                </svg>
                            </span>
                            <input type="password" name="password" placeholder="Password" autocomplete="current-password">
                        </div>

                        <div class="form-options">
                            <label class="remember-option">
                                <input type="checkbox" name="remember">
                                <span>Remember Me</span>
                            </label>
                            <a href="#">Forgot Password?</a>
                        </div>

                        <button class="primary-button" type="submit">Log In</button>

                        <p class="account-switch">
                            Don't Have an account?
                            <button type="button" data-switch="register">Create one</button>
                        </p>
                    </form>

                    <form class="auth-form" id="register-form" action="#" method="POST">
                        <label class="field-label" for="name">Name <span>*</span></label>
                        <div class="input-box compact">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <circle cx="12" cy="8" r="3.5"></circle>
                                    <path d="M5 20c.6-4 3.1-6 7-6s6.4 2 7 6"></path>
                                </svg>
                            </span>
                            <input id="name" type="text" name="name" placeholder="Name" autocomplete="name">
                        </div>

                        <label class="field-label" for="register-email">Email Address <span>*</span></label>
                        <div class="input-box compact">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <rect x="3" y="5" width="18" height="14" rx="1.8"></rect>
                                    <path d="m4 7 8 6 8-6"></path>
                                </svg>
                            </span>
                            <input id="register-email" type="email" name="register_email" placeholder="Email Address" autocomplete="email">
                        </div>

                        <label class="field-label" for="register-password">Password <span>*</span></label>
                        <div class="input-box compact">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                </svg>
                            </span>
                            <input id="register-password" type="password" name="register_password" placeholder="Password" autocomplete="new-password">
                        </div>
                        <p class="password-help">Password must be at least 8 letters, numbers, and symbols.</p>

                        <label class="field-label" for="confirm-password">Confirm Password <span>*</span></label>
                        <div class="input-box compact">
                            <span class="field-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                </svg>
                            </span>
                            <input id="confirm-password" type="password" name="password_confirmation" placeholder="Password" autocomplete="new-password">
                        </div>

                        <button class="primary-button register-button" type="submit">Create Account</button>

                        <p class="account-switch register-switch">
                            Already have an account?
                            <button type="button" data-switch="login">Log In</button>
                        </p>
                    </form>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
