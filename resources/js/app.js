import './bootstrap';
import '../css/chat-modal.css';
import './chat-modal';
import jQuery from 'jquery';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.$ = window.jQuery = jQuery; // Make jQuery globally available

Alpine.start();

import './datatable_init'; // Import datatable_init.js after making jQuery globally available