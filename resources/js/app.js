import './bootstrap';

import Alpine from 'alpinejs';
import { mufiseConnection } from './mufise-connection';
import { mufiseCheckout } from './mufise-checkout';

window.Alpine = Alpine;
window.mufiseConnection = mufiseConnection;
window.mufiseCheckout = mufiseCheckout;

Alpine.start();
