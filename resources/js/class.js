import { auth, db } from './firebase';

import {
    onAuthStateChanged
} from 'firebase/auth';

import {
    collection,
    doc,
    getDoc,
    getDocs
} from 'firebase/firestore';


const classPage =
    document.querySelector('#class-page');

const classId =
    classPage?.dataset.classId;

const className =
    document.querySelector('#class-name');

const subjectCode =
    document.querySelector('#subject-code');

const studentsList =
    document.querySelector('#students-list');

const studentsLoading =
    document.querySelector('#students-loading');

const studentsEmpty =
    document.querySelector('#students-empty');


onAuthStateChanged(auth, async (user) => {

    if (!user) {
        window.location.replace('/');
        return;
    }

    if (!classId) {
        console.error('Class ID is missing.');
        return;
    }

    try {

        // ==================================================
        // GET CLASS
        // ==================================================

        const classReference =
            doc(db, 'classes', classId);

        const classSnapshot =
            await getDoc(classReference);

        if (!classSnapshot.exists()) {
            className.textContent =
                'Class not found';

            return;
        }

        const classData =
            classSnapshot.data();


        // ==================================================
        // SECURITY CHECK
        // ==================================================

        if (classData.teacherId !== user.uid) {
            className.textContent =
                'You do not have access to this class';

            return;
        }


        // ==================================================
        // DISPLAY CLASS INFORMATION
        // ==================================================

        className.textContent =
            classData.className ??
            'Unnamed Class';

        subjectCode.textContent =
            `${classData.subjectCode ?? ''} • ${classData.yearLevel ?? ''}`;


        // ==================================================
        // GET STUDENTS
        // ==================================================

        const studentsReference =
            collection(
                db,
                'classes',
                classId,
                'students'
            );

        const studentsSnapshot =
            await getDocs(
                studentsReference
            );

        studentsLoading.hidden = true;

        studentsList.innerHTML = '';


        // ==================================================
        // NO STUDENTS
        // ==================================================

        if (studentsSnapshot.empty) {
            studentsEmpty.hidden = false;
            return;
        }

        studentsEmpty.hidden = true;


        // ==================================================
        // DISPLAY STUDENTS
        // ==================================================

        studentsSnapshot.forEach(
            (studentDocument) => {

                const student =
                    studentDocument.data();

                const studentCard =
                    document.createElement('div');

                studentCard.className =
                    'student-card';

                const name =
                    document.createElement('strong');

                name.textContent =
                    student.name ??
                    'Student';

                const email =
                    document.createElement('p');

                email.textContent =
                    student.email ?? '';

                studentCard.appendChild(name);
                studentCard.appendChild(email);

                studentsList.appendChild(
                    studentCard
                );
            }
        );


        console.log(
            `Loaded ${studentsSnapshot.size} student(s)`
        );

    } catch (error) {

        console.error(
            'Could not load class:',
            error
        );

        studentsLoading.textContent =
            'Could not load students.';
    }
});