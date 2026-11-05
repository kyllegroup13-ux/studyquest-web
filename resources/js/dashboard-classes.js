import { db } from './firebase';
import {
    addDoc,
    collection,
    deleteDoc,
    doc,
    getDocs,
    query,
    serverTimestamp,
    where,
    writeBatch
} from 'firebase/firestore';
import { showDashboardAlert, showDashboardConfirm } from './dashboard-alert';

export function initializeDashboardClasses(state) {
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
        if (!state.currentTeacher) {
            throw new Error('Teacher is not authenticated.');
        }

        const idToken = await state.currentTeacher.getIdToken();

        const response = await fetch(
            `/class/${encodeURIComponent(classId)}`,
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${idToken}`,
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            }
        );

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                result.message || 'Could not delete this class.'
            );
        }

        return result;
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

        const deleteClassButton = card.querySelector('.delete-class-button');

        deleteClassButton.addEventListener('click', async () => {
            const classTitle =
                card.querySelector('.class-card-top h2').textContent;

            // Save the button reference before awaiting confirmation.
            const confirmed = await showDashboardConfirm(
                `Delete "${classTitle}"? This will permanently remove the class, its posts, and its student list.`,
                {
                    title: 'Delete this class?',
                    confirmLabel: 'Delete class'
                }
            );

            if (!confirmed) {
                menuPopover.hidden = true;
                menuButton.setAttribute('aria-expanded', 'false');
                return;
            }

            deleteClassButton.disabled = true;
            deleteClassButton.textContent = 'Deleting...';

            try {
                await deleteClass(documentId);
                await loadClasses();

            } catch (error) {
                console.error('Class deletion failed:', error);

                deleteClassButton.disabled = false;
                deleteClassButton.innerHTML =
                    '<i class="fa-solid fa-trash" aria-hidden="true"></i> Delete class';

                await showDashboardAlert(
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
                        await showDashboardAlert(
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
        state.classLayout = layout;
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


    // =====================================================
    // SYNCHRONIZE TEACHER PROFILE WITH EXISTING CLASSES
    // =====================================================

    async function syncTeacherProfileToClasses(name, imageUrl) {
        if (!state.currentTeacher) {
            throw new Error('Teacher is not authenticated.');
        }

        const classesQuery = query(
            collection(db, 'classes'),
            where('teacherId', '==', state.currentTeacher.uid)
        );

        const snapshot = await getDocs(classesQuery);

        // Firestore batches have write limits.
        // Use smaller chunks to support multiple classes.
        const chunkSize = 200;

        for (let start = 0; start < snapshot.docs.length; start += chunkSize) {
            const batch = writeBatch(db);

            const chunk = snapshot.docs.slice(start, start + chunkSize);

            chunk.forEach((classDocument) => {
                batch.update(classDocument.ref, {
                    teacherName: name,
                    teacherProfileImageUrl: imageUrl || ''
                });
            });

            await batch.commit();
        }
    }

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
                        state.currentTeacher.uid
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
            setClassLayout(state.classLayout);

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

            if (!state.currentTeacher) {
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
                await showDashboardAlert(
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
                            state.currentTeacher.uid,

                        // Teacher name copied from users/{uid}
                        teacherName:
                            state.currentTeacherName,

                        // Teacher profile image for Flutter People tab
                        teacherProfileImageUrl:
                            state.currentProfileImageUrl || '',

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

                        classCode:
                            classCode,

                        // Initialize class counters
                        studentCount: 0,
                        postCount: 0,

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

                await showDashboardAlert(
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
    return {
        loadClasses,
        syncTeacherProfileToClasses
    };
}
