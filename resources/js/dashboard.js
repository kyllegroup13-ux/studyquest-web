import { auth, db } from './firebase';
import { onAuthStateChanged, signOut } from 'firebase/auth';
import { doc, getDoc } from 'firebase/firestore';
import { initializeDashboardClasses } from './dashboard-classes';
import { initializeDashboardProfile } from './dashboard-profile';
import { showDashboardAlert } from './dashboard-alert';

const dashboardPage = document.querySelector('.dashboard-page');

if (dashboardPage) {
    const state = {
        currentTeacher: null,
        currentProfileImageUrl: '',
        selectedProfileImagePreviewUrl: '',
        currentTeacherName: 'Teacher',
        classLayout: 'grid'
    };

    const classes = initializeDashboardClasses(state);
    const profile = initializeDashboardProfile(
        state,
        classes.syncTeacherProfileToClasses
    );

    const logoutButton = document.querySelector('#logout-button');
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

                await showDashboardAlert(
                    'Please verify your email before accessing the dashboard.'
                );

                window.location.replace('/');

                return;
            }


            /* =============================================
               SAVE CURRENT TEACHER
            ============================================= */

            state.currentTeacher = user;


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

                    profile.setProfileImage(
                        userData.profileImageUrl || user.photoURL || ''
                    );
                } else {
                    profile.setProfileImage(user.photoURL || '');
                }

            } catch (error) {
                console.error(error);
                profile.setProfileImage(user.photoURL || '');
            }


            /* =============================================
               UPDATE PROFILE UI
            ============================================= */

            profile.setProfileEmail(user.email || '');

            profile.setProfileName(teacherName);

            const requestedProfileMode =
                new URLSearchParams(window.location.search).get('profile');

            if (
                requestedProfileMode === 'display' ||
                requestedProfileMode === 'edit'
            ) {
                document.querySelector(
                    requestedProfileMode === 'edit'
                        ? '#edit-profile-button'
                        : '#display-profile-button'
                )?.click();

                const url = new URL(window.location.href);
                url.searchParams.delete('profile');
                window.history.replaceState(
                    null,
                    '',
                    `${url.pathname}${url.search}${url.hash}`
                );
            }


            /* =============================================
               LOAD CLASSES
            ============================================= */

            await classes.loadClasses();
        }
    );

}
