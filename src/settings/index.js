import { createRoot } from '@wordpress/element';
import App from './App';
import './settings.css';

const root = document.getElementById('linkdigest-settings-root');
if (root) {
  createRoot(root).render(<App />);
}
