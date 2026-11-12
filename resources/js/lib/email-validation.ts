import type { FormEvent } from 'react';

/**
 * Replaces the browser's English message for an invalid e-mail with the product's Portuguese text.
 * The custom validity is cleared on input, so a corrected value is accepted again.
 */
export const EMAIL_INVALID_MESSAGE = 'Informe um e-mail válido, como nome@empresa.com.br.';

export const emailValidationProps = {
    onInvalid: (event: FormEvent<HTMLInputElement>) => event.currentTarget.setCustomValidity(EMAIL_INVALID_MESSAGE),
    onInput: (event: FormEvent<HTMLInputElement>) => event.currentTarget.setCustomValidity(''),
};
