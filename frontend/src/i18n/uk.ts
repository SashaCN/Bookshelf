export default {
  app: {
    name: 'Bookshelf',
    tagline: 'Трекер читання і цитат',
  },
  auth: {
    fields: {
      name: 'Ім’я',
      email: 'Email',
      password: 'Пароль',
    },
    hints: {
      password: 'Щонайменше 8 символів',
    },
    login: {
      title: 'Вхід',
      submit: 'Увійти',
      noAccount: 'Ще немає акаунта?',
      toRegister: 'Зареєструватися',
    },
    register: {
      title: 'Реєстрація',
      submit: 'Створити акаунт',
      haveAccount: 'Вже є акаунт?',
      toLogin: 'Увійти',
    },
    errors: {
      invalidCredentials: 'Невірний email або пароль.',
      tooManyAttempts: 'Забагато спроб. Спробуйте за хвилину.',
      emailTaken: 'Акаунт з таким email уже існує.',
      passwordTooShort: 'Пароль має містити щонайменше 8 символів.',
      generic: 'Не вдалося виконати запит. Спробуйте ще раз.',
    },
  },
  home: {
    title: 'Читаю',
    greeting: 'Вітаємо, {name}!',
    placeholder: 'Тут з’являтимуться книги, які ви зараз читаєте. Бібліотека буде на наступному етапі.',
    logout: 'Вийти',
  },
}
