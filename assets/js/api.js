// assets/js/api.js - API клиент для работы с REST сервисом

const API = {
    // Базовый URL API
    baseUrl: '/api',
    
    // Текущий пользователь (для авторизации)
    currentUser: null,
    
    // Установка авторизации
    setAuth(login, password) {
        this.currentUser = { login, password };
    },
    
    // Очистка авторизации
    clearAuth() {
        this.currentUser = null;
    },
    
    // Получение заголовков для запроса
    getHeaders(isFormData = false) {
        const headers = {};
        
        if (isFormData) {
            // Для FormData не устанавливаем Content-Type
        } else {
            headers['Content-Type'] = 'application/json';
        }
        
        // Добавляем Basic Auth если есть
        if (this.currentUser) {
            const credentials = btoa(`${this.currentUser.login}:${this.currentUser.password}`);
            headers['Authorization'] = `Basic ${credentials}`;
        }
        
        return headers;
    },
    
    // Создание новой анкеты (POST)
    async createApplication(data) {
        const response = await fetch(`${this.baseUrl}`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(data)
        });
        
        return await response.json();
    },
    
    // Обновление анкеты (PUT)
    async updateApplication(id, data) {
        const response = await fetch(`${this.baseUrl}/${id}`, {
            method: 'PUT',
            headers: this.getHeaders(),
            body: JSON.stringify(data)
        });
        
        return await response.json();
    },
    
    // Получение анкеты (GET)
    async getApplication(id) {
        const response = await fetch(`${this.baseUrl}/${id}`, {
            method: 'GET',
            headers: this.getHeaders()
        });
        
        return await response.json();
    },
    
    // Отправка формы с валидацией на клиенте
    async submitForm(formElement, options = {}) {
        const formData = new FormData(formElement);
        const formObject = {};
        
        // Преобразование FormData в объект
        for (let [key, value] of formData.entries()) {
            if (key === 'languages[]') {
                if (!formObject.languages) formObject.languages = [];
                formObject.languages.push(value);
            } else if (key === 'csrf_token') {
                // Игнорируем CSRF токен для API
                continue;
            } else {
                formObject[key] = value;
            }
        }
        
        // Валидация на клиенте
        const validationErrors = this.validateForm(formObject);
        if (Object.keys(validationErrors).length > 0) {
            return {
                success: false,
                errors: validationErrors
            };
        }
        
        // Определяем метод (создание или обновление)
        if (options.applicationId && this.currentUser) {
            // Обновление существующей анкеты
            return await this.updateApplication(options.applicationId, formObject);
        } else {
            // Создание новой анкеты
            return await this.createApplication(formObject);
        }
    },
    
    // Клиентская валидация формы
    validateForm(data) {
        const errors = {};
        
        // ФИО
        if (!data.full_name || data.full_name.trim().length < 2) {
            errors.full_name = 'ФИО обязательно для заполнения (минимум 2 символа)';
        } else if (!/^[а-яА-ЯёЁa-zA-Z\s-]{2,150}$/u.test(data.full_name)) {
            errors.full_name = 'ФИО должно содержать только буквы, пробелы и дефисы';
        }
        
        // Телефон
        if (!data.phone) {
            errors.phone = 'Телефон обязателен для заполнения';
        } else if (!/^[0-9+\-\s]{10,20}$/.test(data.phone)) {
            errors.phone = 'Телефон должен содержать только цифры, +, - и пробелы';
        }
        
        // Email
        if (!data.email) {
            errors.email = 'Email обязателен для заполнения';
        } else if (!/^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/.test(data.email)) {
            errors.email = 'Введите корректный email адрес';
        }
        
        // Дата рождения
        if (!data.birth_date) {
            errors.birth_date = 'Дата рождения обязательна';
        } else if (!/^\d{4}-\d{2}-\d{2}$/.test(data.birth_date)) {
            errors.birth_date = 'Неверный формат даты';
        }
        
        // Пол
        if (!data.gender) {
            errors.gender = 'Выберите пол';
        }
        
        // Языки программирования
        if (!data.languages || data.languages.length === 0) {
            errors.languages = 'Выберите хотя бы один язык программирования';
        }
        
        return errors;
    },
    
    // Отображение ошибок валидации на форме
    displayErrors(errors, formElement) {
        // Удаляем старые сообщения об ошибках
        formElement.querySelectorAll('.error-message').forEach(el => el.remove());
        formElement.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        
        // Отображаем новые ошибки
        for (const [field, message] of Object.entries(errors)) {
            let inputElement;
            
            if (field === 'languages') {
                inputElement = formElement.querySelector('[name="languages[]"]');
            } else if (field === 'gender') {
                inputElement = formElement.querySelector(`[name="gender"]:checked`)?.parentElement?.parentElement;
            } else {
                inputElement = formElement.querySelector(`[name="${field}"]`);
            }
            
            if (inputElement) {
                const formGroup = inputElement.closest('.form-group') || inputElement.parentElement;
                if (formGroup) {
                    formGroup.classList.add('has-error');
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = message;
                    formGroup.appendChild(errorDiv);
                }
            }
        }
    },
    
    // Показ сообщения
    showMessage(element, message, type = 'success') {
        if (!element) return;
        
        element.textContent = message;
        element.className = `form-message ${type}`;
        element.classList.remove('hidden');
        
        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            if (element) {
                element.classList.add('hidden');
            }
        }, 5000);
    }
};

// Экспорт для использования
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}