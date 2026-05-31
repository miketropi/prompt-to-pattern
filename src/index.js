/**
 * Prompt to Pattern — Editor entry point.
 *
 * Registered as a Gutenberg plugin; renders the ComposePanel sidebar.
 */
import { registerPlugin } from '@wordpress/plugins';
import './store';
import ComposePanel from './components/ComposePanel';

registerPlugin( 'prompt-to-pattern', {
	render: ComposePanel,
} );
