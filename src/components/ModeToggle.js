/**
 * ModeToggle — switches between Mode A (patterns only) and Mode B (allow generation).
 */
import { ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export default function ModeToggle() {
	const allowGeneration = useSelect( ( select ) =>
		select( 'prompt-to-pattern' ).getAllowGeneration()
	);
	const { setAllowGeneration } = useDispatch( 'prompt-to-pattern' );

	return (
		<ToggleControl
			label={ __( 'Allow AI-generated patterns', 'prompt-to-pattern' ) }
			help={
				allowGeneration
					? __(
							'AI may generate new block markup when no pattern matches.',
							'prompt-to-pattern'
					  )
					: __(
							'Only existing patterns will be used.',
							'prompt-to-pattern'
					  )
			}
			checked={ allowGeneration }
			onChange={ ( value ) => setAllowGeneration( value ) }
		/>
	);
}
