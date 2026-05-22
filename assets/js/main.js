// assets/js/main.js - упрощенная рабочая версия

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM загружен');
    
    // Инициализация формы
    const form = document.getElementById('application-form');
    
    if (form) {
        console.log('Форма найдена');
        
        // Убираем стандартную отправку
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Форма отправлена');
            
            // Показываем индикатор загрузки
            const submitBtn = document.getElementById('submit-btn');
            const spinner = document.getElementById('form-spinner');
            const messageDiv = document.getElementById('form-message');
            
            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('hidden');
            if (messageDiv) {
                messageDiv.classList.add('hidden');
                messageDiv.className = 'form-message';
            }
            
            // Собираем данные формы
            const formData = new FormData(form);
            const applicationData = {
                full_name: formData.get('full_name'),
                phone: formData.get('phone'),
                email: formData.get('email'),
                birth_date: formData.get('birth_date'),
                gender: formData.get('gender'),
                biography: formData.get('biography') || '',
                languages: formData.getAll('languages[]')
            };
            
            console.log('Отправляем данные:', applicationData);
            
            // Простая валидация
            const errors = validateForm(applicationData);
            
            if (Object.keys(errors).length > 0) {
                showErrors(errors);
                if (messageDiv) {
                    messageDiv.textContent = 'Пожалуйста, исправьте ошибки в форме';
                    messageDiv.classList.add('error');
                    messageDiv.classList.remove('hidden');
                }
                if (submitBtn) submitBtn.disabled = false;
                if (spinner) spinner.classList.add('hidden');
                return;
            }
            
            // Отправка на сервер
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(applicationData)
                });
                
                const result = await response.json();
                console.log('Ответ сервера:', result);
                
                if (result.success) {
                    // Успех
                    form.reset();
                    
                    if (messageDiv) {
                        messageDiv.textContent = result.message || 'Анкета успешно отправлена!';
                        messageDiv.classList.add('success');
                        messageDiv.classList.remove('hidden');
                    }
                    
                    // Показываем логин и пароль
                    if (result.data && result.data.login && result.data.password) {
                        showCredentials(result.data.login, result.data.password);
                        
                        // Сохраняем в localStorage
                        localStorage.setItem('app_login', result.data.login);
                        localStorage.setItem('app_password', result.data.password);
                        localStorage.setItem('app_id', result.data.application_id);
                    }
                } else {
                    // Ошибка от сервера
                    if (result.errors) {
                        showErrors(result.errors);
                        if (messageDiv) {
                            messageDiv.textContent = 'Пожалуйста, исправьте ошибки';
                            messageDiv.classList.add('error');
                            messageDiv.classList.remove('hidden');
                        }
                    } else if (result.error) {
                        if (messageDiv) {
                            messageDiv.textContent = result.error;
                            messageDiv.classList.add('error');
                            messageDiv.classList.remove('hidden');
                        }
                    }
                }
                
            } catch (error) {
                console.error('Ошибка:', error);
                if (messageDiv) {
                    messageDiv.textContent = 'Ошибка соединения: ' + error.message;
                    messageDiv.classList.add('error');
                    messageDiv.classList.remove('hidden');
                }
            } finally {
                if (submitBtn) submitBtn.disabled = false;
                if (spinner) spinner.classList.add('hidden');
            }
        });
    } else {
        console.error('Форма с id="application-form" не найдена');
    }
    
    // Функция валидации
    function validateForm(data) {
        const errors = {};
        
        if (!data.full_name || data.full_name.trim().length < 2) {
            errors.full_name = 'ФИО обязательно (минимум 2 символа)';
        } else if (!/^[а-яА-ЯёЁa-zA-Z\s-]{2,150}$/u.test(data.full_name)) {
            errors.full_name = 'ФИО должно содержать только буквы, пробелы и дефисы';
        }
        
        if (!data.phone) {
            errors.phone = 'Телефон обязателен';
        } else if (!/^[0-9+\-\s]{10,20}$/.test(data.phone)) {
            errors.phone = 'Неверный формат телефона';
        }
        
        if (!data.email) {
            errors.email = 'Email обязателен';
        } else if (!/^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/.test(data.email)) {
            errors.email = 'Неверный формат email';
        }
        
        if (!data.birth_date) {
            errors.birth_date = 'Дата рождения обязательна';
        }
        
        if (!data.gender) {
            errors.gender = 'Выберите пол';
        }
        
        if (!data.languages || data.languages.length === 0) {
            errors.languages = 'Выберите хотя бы один язык';
        }
        
        return errors;
    }
    
    // Функция показа ошибок
    function showErrors(errors) {
        // Удаляем старые ошибки
        document.querySelectorAll('.error-message').forEach(el => el.remove());
        document.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        
        // Добавляем новые
        for (const [field, message] of Object.entries(errors)) {
            let input;
            if (field === 'languages') {
                input = document.querySelector('[name="languages[]"]');
            } else {
                input = document.querySelector(`[name="${field}"]`);
            }
            
            if (input) {
                const formGroup = input.closest('.form-group');
                if (formGroup) {
                    formGroup.classList.add('has-error');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = message;
                    formGroup.appendChild(errorDiv);
                }
            }
        }
    }
    
    // Функция показа логина и пароля
    function showCredentials(login, password) {
        const container = document.getElementById('credentials-container');
        const loginSpan = document.getElementById('generated-login');
        const passwordSpan = document.getElementById('generated-password');
        
        if (container && loginSpan && passwordSpan) {
            loginSpan.textContent = login;
            passwordSpan.textContent = password;
            container.classList.remove('hidden');
            
            // Прокрутка к контейнеру
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // Инициализация слайдера и других компонентов
    initSlider();
    initMobileMenu();
    initSmoothScroll();
});

// Остальные функции (слайдер, меню и т.д.)
function initSlider() {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.getElementById('prev-slide');
    const nextBtn = document.getElementById('next-slide');
    
    if (!slides.length) return;
    
    let currentSlide = 0;
    const totalSlides = slides.length;
    
    function showSlide(index) {
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        slides[index].classList.add('active');
        if (dots[index]) dots[index].classList.add('active');
        currentSlide = index;
    }
    
    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        showSlide(currentSlide);
    }
    
    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        showSlide(currentSlide);
    }
    
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => showSlide(index));
    });
    
    let slideInterval = setInterval(nextSlide, 5000);
    const sliderContainer = document.querySelector('.slider-container');
    if (sliderContainer) {
        sliderContainer.addEventListener('mouseenter', () => clearInterval(slideInterval));
        sliderContainer.addEventListener('mouseleave', () => {
            slideInterval = setInterval(nextSlide, 5000);
        });
    }
}

function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const navMenu = document.getElementById('nav-menu');
    
    if (!mobileMenuBtn || !navMenu) return;
    
    mobileMenuBtn.addEventListener('click', function() {
        navMenu.classList.toggle('active');
    });
}

function initSmoothScroll() {
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
}