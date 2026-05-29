/**
 * Block editor sidebar entry.
 */
import { registerPlugin } from '@wordpress/plugins';
import EditorSidebar from './EditorSidebar';

registerPlugin( 'assetpilot', {
	render: EditorSidebar,
	icon: 'performance',
} );
