/**
 * TargetToggle — switches output target between page content and FSE template.
 */
import { SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const OPTIONS = [
	{
		label: __( 'Page content', 'prompt-to-pattern' ),
		value: 'page_content',
	},
	{
		label: __( 'FSE template', 'prompt-to-pattern' ),
		value: 'fse_template',
	},
];

export default function TargetToggle() {
	const target = useSelect( ( select ) =>
		select( 'prompt-to-pattern' ).getTarget()
	);
	const { setTarget } = useDispatch( 'prompt-to-pattern' );

	return (
		<SelectControl
			label={ __( 'Output target', 'prompt-to-pattern' ) }
			value={ target }
			options={ OPTIONS }
			onChange={ ( value ) => setTarget( value ) }
		/>
	);
}
