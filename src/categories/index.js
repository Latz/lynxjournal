import { createRoot } from '@wordpress/element';
import App from './App';

const root = document.getElementById('lynxjournal-categories-root');
if (root) {
  createRoot(root).render(<App />);
}
