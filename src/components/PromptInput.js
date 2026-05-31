/**
 * PromptInput — textarea for the natural-language prompt.
 */
import { TextareaControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export default function PromptInput() {
	const prompt = useSelect( ( select ) =>
		select( 'prompt-to-pattern' ).getPrompt()
	);
	const { setPrompt } = useDispatch( 'prompt-to-pattern' );

	return (
		<TextareaControl
			label={ __( 'Describe your page', 'prompt-to-pattern' ) }
			help={ __(
				'Example: "A pricing page with a hero, three-tier pricing table, FAQ, and call to action."',
				'prompt-to-pattern'
			) }
			value={ prompt }
			onChange={ ( value ) => setPrompt( value ) }
			rows={ 4 }
		/>
	);
}
