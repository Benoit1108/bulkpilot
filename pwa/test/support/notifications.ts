import type { AppNotification, NotificationSettings } from '~/features/notifications/notifications'

let next = 0

export function notification(override: Partial<AppNotification> = {}): AppNotification {
  next++
  return {
    id: `0199a0b1-0000-7000-8000-${String(next).padStart(12, '0')}`,
    category: 'low_calories',
    title: 'Calories très insuffisantes',
    body: 'Tu es à 1\u00A0540 / 2\u00A0350\u00A0kcal\u00A0: il reste environ 810\u00A0kcal.',
    link: '/nutrition',
    createdAt: '2026-09-30T18:05:00+00:00',
    readAt: null,
    ...override,
  }
}

export function notificationSettings(override: Partial<NotificationSettings> = {}): NotificationSettings {
  return {
    categories: { low_calories: true, workout_not_started: true, missing_weigh_in: true, goal_reached: true, record: true, weekly_report: true },
    quietFrom: '23:00',
    quietTo: '08:00',
    ...override,
  }
}
