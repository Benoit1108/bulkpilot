import { registerErrorMessages } from '~/services/api/messages'
import { PASSWORD_MIN_LENGTH } from './rules'

registerErrorMessages({
  'identity.invalid_credentials': 'Email ou mot de passe incorrect.',
  'identity.invalid_email': 'Cette adresse email n’est pas valide.',
  'identity.email_already_used': 'Un compte existe déjà avec cette adresse email.',
  'identity.registration_closed': 'Les inscriptions sont fermées pour le moment.',
  'identity.weak_password': `Le mot de passe doit contenir au moins ${PASSWORD_MIN_LENGTH} caractères.`,
  'identity.wrong_current_password': 'Le mot de passe actuel est incorrect.',
  'identity.invalid_token': 'Ce lien est invalide, expiré ou a déjà été utilisé.',
  'identity.email_already_verified': 'Ton adresse email est déjà confirmée.',
  'identity.email_not_verified': 'Confirme d’abord ton adresse email.',
  'identity.invalid_profile': 'Certaines informations du profil ne sont pas plausibles.',
  'identity.user_not_found': 'Ce compte n’existe plus.',
  'identity.session_not_found': 'Cet appareil est déjà déconnecté.',
  'identity.invalid_second_factor_code': 'Code invalide ou déjà utilisé. Attends le code suivant de ton application.',
  'identity.second_factor_not_pending': 'La connexion a expiré. Recommence avec ton mot de passe.',
  'identity.two_factor_already_enabled': 'La double authentification est déjà active.',
  'identity.two_factor_not_enabled': 'La double authentification n’est pas active.',
  'identity.two_factor_setup_not_started': 'Recommence l’activation depuis le début.',
  'identity.external_account_conflict': 'Un compte non confirmé utilise déjà cette adresse. Connecte-toi avec ton mot de passe.',
  'identity.external_sign_in_failed': 'La connexion avec ce service a échoué. Réessaie.',
  'identity.external_sign_in_cancelled': 'Connexion annulée.',
  'identity.external_provider_unavailable': 'Ce mode de connexion n’est pas disponible.',
})
