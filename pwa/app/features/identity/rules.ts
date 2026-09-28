/**
 * Business limits mirrored from the API (Identity domain) for hints and input attributes only:
 * the API enforces them.
 */
export const PASSWORD_MIN_LENGTH = 12 // Identity\Domain\User\PasswordPolicy::MIN_LENGTH
export const HEIGHT_CM = { min: 100, max: 250 } as const // Identity\Domain\User\Profile
export const PASSWORD_HINT = `Au moins ${PASSWORD_MIN_LENGTH} caractères. Une phrase facile à retenir est idéale.`
