import { db } from './firebase';
import { updateProfile } from 'firebase/auth';
import { doc, updateDoc } from 'firebase/firestore';
import { showDashboardAlert } from './dashboard-alert';

export function initializeDashboardProfile(state, syncTeacherProfileToClasses) {
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


    function setProfileName(name) {
        state.currentTeacherName = name;
        const initial = name.charAt(0).toUpperCase() || 'T';

        profileName.textContent = name;
        profileDetailName.textContent = name;
        profileInitial.textContent = initial;
        profileMenuInitial.textContent = initial;
        profileModalInitial.textContent = initial;
        profileEditInitial.textContent = initial;
    }

    function setProfileImage(url) {
        state.currentProfileImageUrl = url || '';

        [
            profileButtonImage,
            profileMenuImage,
            profileModalImage
        ].forEach((image) => {
            if (state.currentProfileImageUrl) {
                image.src = state.currentProfileImageUrl;
            } else {
                image.removeAttribute('src');
            }

            image.hidden = !state.currentProfileImageUrl;
        });

        profileInitial.hidden = Boolean(state.currentProfileImageUrl);
        profileMenuInitial.hidden = Boolean(state.currentProfileImageUrl);
        profileModalInitial.hidden = Boolean(state.currentProfileImageUrl);
    }

    function setProfileEmail(email) {
        profileEmail.textContent = email;
        profileDetailEmail.textContent = email;
        profileEditEmail.value = email;
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
        if (state.selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(state.selectedProfileImagePreviewUrl);
            state.selectedProfileImagePreviewUrl = '';
        }

        profileEditImageInput.value = '';
        setProfileEditPreview(state.currentProfileImageUrl);
    }

    async function uploadProfileImage(file) {
        const idToken = await state.currentTeacher.getIdToken();
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
            profileEditName.value = state.currentTeacherName;
            profileEditEmail.value = state.currentTeacher?.email || '';
            clearSelectedProfileImage();
            profileEditName.focus();
        }
    }

    function openProfileModal(mode = 'display') {
        profileMenu.hidden = true;
        profileButton.setAttribute('aria-expanded', 'false');
        profileDetailEmail.textContent = state.currentTeacher?.email || '';
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
        if (state.selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(state.selectedProfileImagePreviewUrl);
            state.selectedProfileImagePreviewUrl = '';
        }

        profileEditImageInput.value = '';
        setProfileModalMode('display');
    }


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

    profileEditImageInput.addEventListener('change', async () => {
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
            await showDashboardAlert('Choose a JPG, PNG, or WebP image smaller than 5 MB.');
            return;
        }

        if (state.selectedProfileImagePreviewUrl) {
            URL.revokeObjectURL(state.selectedProfileImagePreviewUrl);
        }

        state.selectedProfileImagePreviewUrl = URL.createObjectURL(file);
        setProfileEditPreview(state.selectedProfileImagePreviewUrl);
    });

    profileModal.addEventListener('click', (event) => {
        if (event.target === profileModal) {
            closeProfileModal();
        }
    });

    profileEditForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = profileEditName.value.trim();
        if (!name || !state.currentTeacher) {
            profileEditName.focus();
            return;
        }

        saveProfileButton.disabled = true;
        saveProfileButton.textContent = 'Saving...';

        let updatedProfileImageUrl = state.currentProfileImageUrl;
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
                doc(db, 'users', state.currentTeacher.uid),
                {
                    name,
                    profileImageUrl: updatedProfileImageUrl
                }
            );
            firestoreProfileSaved = true;

            // Update teacher information in all owned classes.
            let classesSynchronized = true;

            try {
                await syncTeacherProfileToClasses(
                    name,
                    updatedProfileImageUrl
                );
            } catch (syncError) {
                classesSynchronized = false;
                console.error(
                    'Teacher profile saved, but class synchronization failed:',
                    syncError
                );
            }

            await updateProfile(state.currentTeacher, {
                displayName: name,
                photoURL: updatedProfileImageUrl || null
            });

            setProfileName(name);
            setProfileImage(updatedProfileImageUrl);
            profileDetailEmail.textContent =
                state.currentTeacher.email || '';
            profileEmail.textContent =
                state.currentTeacher.email || '';
            closeProfileModal();
            if (!classesSynchronized) {
                await showDashboardAlert(
                    'Your profile was saved, but some class profiles may not have updated. Please try saving your profile again.'
                );
            }
        } catch (error) {
            console.error(error);
            if (firestoreProfileSaved) {
                setProfileName(name);
                setProfileImage(updatedProfileImageUrl);
                closeProfileModal();
                await showDashboardAlert('Your profile was saved, but could not be synchronized with your sign-in profile.');
            } else {
                await showDashboardAlert(error.message || 'Could not update your profile. Please try again.');
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

    return {
        setProfileEmail,
        setProfileName,
        setProfileImage
    };
}
