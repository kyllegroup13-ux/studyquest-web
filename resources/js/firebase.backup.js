import { initializeApp } from 'firebase/app';
import { getAuth } from 'firebase/auth';
import { getFirestore } from 'firebase/firestore';

const firebaseConfig = {
  apiKey: "AIzaSyC7R_9PbQZQ8WTN4Jm0EgnwfHLAA7F6X1Y",
  authDomain: "studyquest-1e76e.firebaseapp.com",
  projectId: "studyquest-ac732",
  storageBucket: "studyquest-1e76e.firebasestorage.app",
  messagingSenderId: "35296442691",
  appId: "1:35296442691:web:1dbb87825ce0009d4857f6"
};

const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);
export const db = getFirestore(app);