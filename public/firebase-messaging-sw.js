importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyChuN8uhhXITpYUPLXa9foHeeIwtN4BMco",
    authDomain: "rebenney-luxury.firebaseapp.com",
    projectId: "rebenney-luxury",
    storageBucket: "rebenney-luxury.firebasestorage.app",
    messagingSenderId: "172731296500",
    appId: "1:172731296500:web:ca5b5eeed42e044f75cca1",
    measurementId: "G-P5TTD83V0K"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});