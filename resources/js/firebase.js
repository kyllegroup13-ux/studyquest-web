import { initializeApp } from 'firebase/app';
import { getAuth } from 'firebase/auth';
import { getFirestore } from 'firebase/firestore';

const firebaseConfig = {
  apiKey: "AIzaSyD7TGb4R-bMM7q4dBMIVE2INZZYZMzvikI",
  authDomain: "studyquest-ac732.firebaseapp.com",
  projectId: "studyquest-ac732",
  storageBucket: "studyquest-ac732.firebasestorage.app",
  messagingSenderId: "592168830439",
  appId: "1:592168830439:web:2c721bbd32f264c5996942"
};

const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);
export const db = getFirestore(app);