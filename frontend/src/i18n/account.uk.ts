// Strings of the account security screens: resetting a forgotten password, changing it, deleting the account.
export default {
  login: {
    forgotPassword: 'Забули пароль?',
    passwordChanged: 'Пароль змінено, увійдіть з новим паролем',
    accountDeleted: 'Акаунт видалено',
  },
  forgot: {
    title: 'Відновлення пароля',
    intro: 'Вкажіть email акаунта, і ми надішлемо посилання для нового пароля.',
    submit: 'Надіслати посилання',
    sent: 'Якщо такий акаунт існує, ми надіслали лист із посиланням',
    toLogin: 'Повернутися до входу',
  },
  reset: {
    title: 'Новий пароль',
    newPassword: 'Новий пароль',
    confirmPassword: 'Повторіть пароль',
    submit: 'Змінити пароль',
  },
  errors: {
    invalidResetLink: 'Посилання недійсне або застаріло. Запросіть нове.',
    passwordMismatch: 'Паролі не збігаються.',
  },
  password: {
    title: 'Змінити пароль',
    current: 'Поточний пароль',
    new: 'Новий пароль',
    confirm: 'Підтвердіть новий пароль',
    submit: 'Змінити пароль',
    done: 'Пароль змінено. На інших пристроях потрібно буде увійти знову.',
    currentWrong: 'Поточний пароль невірний.',
    newInvalid: 'Новий пароль має містити щонайменше 8 символів.',
    mismatch: 'Паролі не збігаються.',
  },
  danger: {
    title: 'Небезпечна зона',
    text: 'Видалення акаунта стирає всі ваші дані без можливості відновлення.',
    open: 'Видалити акаунт',
    dialogTitle: 'Видалити акаунт?',
    dialogIntro: 'Буде остаточно видалено:',
    items: {
      library: 'уся бібліотека та історія читання',
      quotes: 'цитати та нотатки',
      community: 'ваші публікації, відгуки, коментарі й лайки',
      books: 'завантажені книги та місце, де ви зупинилися',
      follows: 'підписки й підписники',
    },
    irreversible: 'Цю дію неможливо скасувати.',
    password: 'Введіть пароль, щоб підтвердити',
    passwordWrong: 'Пароль невірний.',
    confirm: 'Видалити назавжди',
  },
}
