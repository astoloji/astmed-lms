if (response.success) {
                    $btn.toggleClass('favorited');
                    const icon = $btn.find('i');
                    if ($btn.hasClass('favorited')) {
                        icon.removeClass('far').addClass('fas');
                        ASTMED_LMS.NotificationModule.show('Favorilere eklendi', 'success');
                    } else {
                        icon.removeClass('fas').addClass('far');
                        ASTMED_LMS.NotificationModule.show('Favorilerden çıkarıldı', 'info');
                    }
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show('Bir hata oluştu', 'error');
            }
        },

        adjustLayout: function() {
            // Responsive düzenlemeler
            const windowWidth = $(window).width();
            if (windowWidth < 768) {
                $('.astmed-courses-grid').removeClass('grid-3').addClass('grid-1');
            } else if (windowWidth < 1024) {
                $('.astmed-courses-grid').removeClass('grid-3 grid-1').addClass('grid-2');
            } else {
                $('.astmed-courses-grid').removeClass('grid-1 grid-2').addClass('grid-3');
            }
        }
    };

    // Quiz Modülü
    ASTMED_LMS.QuizModule = {
        timer: null,
        timeRemaining: 0,
        currentQuestion: 0,
        answers: {},

        init: function() {
            this.bindEvents();
            this.initTimer();
            this.loadProgress();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-quiz-start', this.startQuiz);
            $(document).on('change', '.astmed-question input[type="radio"]', this.saveAnswer);
            $(document).on('click', '.astmed-quiz-next', this.nextQuestion);
            $(document).on('click', '.astmed-quiz-prev', this.prevQuestion);
            $(document).on('click', '.astmed-quiz-submit', this.submitQuiz);
            $(document).on('click', '.astmed-quiz-retake', this.retakeQuiz);
        },

        startQuiz: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const quizId = $btn.data('quiz-id');
            
            // Onay modal'ı göster
            ASTMED_LMS.ModalModule.show({
                title: 'Quiz\'e Başla',
                content: 'Quiz\'e başlamak istediğinizden emin misiniz? Süre sınırı vardır.',
                actions: [
                    {
                        text: 'İptal',
                        class: 'astmed-lms-button-outline',
                        action: () => ASTMED_LMS.ModalModule.hide()
                    },
                    {
                        text: 'Başla',
                        class: 'astmed-lms-button-primary',
                        action: () => {
                            ASTMED_LMS.ModalModule.hide();
                            ASTMED_LMS.QuizModule.initQuiz(quizId);
                        }
                    }
                ]
            });
        },

        initQuiz: async function(quizId) {
            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_start_quiz',
                    quiz_id: quizId,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    const quizData = response.data;
                    this.timeRemaining = quizData.time_limit * 60; // dakikayı saniyeye çevir
                    this.currentQuestion = 0;
                    this.answers = {};
                    
                    this.renderQuiz(quizData);
                    this.startTimer();
                    
                    ASTMED_LMS.trackEvent('quiz_started', { quiz_id: quizId });
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show('Quiz başlatılamadı', 'error');
            }
        },

        renderQuiz: function(quizData) {
            const $container = $('.astmed-quiz-container');
            let html = `
                <div class="astmed-quiz-header">
                    <h2 class="astmed-quiz-title">${quizData.title}</h2>
                    <div class="astmed-quiz-meta">
                        <span>Soru Sayısı: ${quizData.questions.length}</span>
                        <span>Süre: ${quizData.time_limit} dakika</span>
                        <span>Geçme Notu: %${quizData.pass_percentage}</span>
                    </div>
                </div>
                <div class="astmed-quiz-timer" id="quiz-timer">
                    <span>Kalan Süre: <strong id="time-display">--:--</strong></span>
                </div>
                <div class="astmed-quiz-questions">
            `;

            quizData.questions.forEach((question, index) => {
                html += this.renderQuestion(question, index);
            });

            html += `
                </div>
                <div class="astmed-quiz-navigation">
                    <button class="astmed-lms-button astmed-lms-button-outline astmed-quiz-prev" disabled>
                        ← Önceki
                    </button>
                    <span class="astmed-question-counter">
                        <span id="current-question">1</span> / ${quizData.questions.length}
                    </span>
                    <button class="astmed-lms-button astmed-lms-button-primary astmed-quiz-next">
                        Sonraki →
                    </button>
                    <button class="astmed-lms-button astmed-lms-button-success astmed-quiz-submit" style="display: none;">
                        Quiz'i Tamamla
                    </button>
                </div>
            `;

            $container.html(html);
            this.showQuestion(0);
        },

        renderQuestion: function(question, index) {
            let html = `
                <div class="astmed-question" data-question="${index}" style="display: ${index === 0 ? 'block' : 'none'}">
                    <div class="astmed-question-number">Soru ${index + 1}</div>
                    <div class="astmed-question-text">${question.question}</div>
                    <div class="astmed-question-options">
            `;

            if (question.type === 'multiple_choice') {
                question.answers.forEach((answer, answerIndex) => {
                    html += `
                        <label class="astmed-option">
                            <input type="radio" name="question_${index}" value="${answerIndex}" />
                            <span>${answer}</span>
                        </label>
                    `;
                });
            } else if (question.type === 'true_false') {
                html += `
                    <label class="astmed-option">
                        <input type="radio" name="question_${index}" value="true" />
                        <span>Doğru</span>
                    </label>
                    <label class="astmed-option">
                        <input type="radio" name="question_${index}" value="false" />
                        <span>Yanlış</span>
                    </label>
                `;
            } else if (question.type === 'fill_blank') {
                html += `
                    <input type="text" name="question_${index}" class="astmed-form-input" placeholder="Cevabınızı yazın..." />
                `;
            }

            html += `
                    </div>
                </div>
            `;

            return html;
        },

        startTimer: function() {
            if (this.timeRemaining <= 0) return;

            this.timer = setInterval(() => {
                this.timeRemaining--;
                this.updateTimerDisplay();

                if (this.timeRemaining <= 0) {
                    this.timeUp();
                }
            }, 1000);

            this.updateTimerDisplay();
        },

        updateTimerDisplay: function() {
            const minutes = Math.floor(this.timeRemaining / 60);
            const seconds = this.timeRemaining % 60;
            const display = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            $('#time-display').text(display);

            // Son 5 dakikada kırmızı yap
            if (this.timeRemaining <= 300) {
                $('#quiz-timer').addClass('warning');
            }

            // Son 1 dakikada yanıp sönsün
            if (this.timeRemaining <= 60) {
                $('#quiz-timer').addClass('critical');
            }
        },

        timeUp: function() {
            clearInterval(this.timer);
            ASTMED_LMS.NotificationModule.show('Süre doldu! Quiz otomatik olarak gönderiliyor.', 'warning');
            setTimeout(() => this.submitQuiz(), 2000);
        },

        saveAnswer: function() {
            const $input = $(this);
            const questionIndex = $input.closest('.astmed-question').data('question');
            const value = $input.val();
            
            ASTMED_LMS.QuizModule.answers[questionIndex] = value;
            
            // Görsel feedback
            $input.closest('.astmed-option').addClass('selected')
                  .siblings().removeClass('selected');

            // Progress kaydet
            ASTMED_LMS.QuizModule.saveProgress();
        },

        nextQuestion: function() {
            const totalQuestions = $('.astmed-question').length;
            
            if (ASTMED_LMS.QuizModule.currentQuestion < totalQuestions - 1) {
                ASTMED_LMS.QuizModule.currentQuestion++;
                ASTMED_LMS.QuizModule.showQuestion(ASTMED_LMS.QuizModule.currentQuestion);
            }
        },

        prevQuestion: function() {
            if (ASTMED_LMS.QuizModule.currentQuestion > 0) {
                ASTMED_LMS.QuizModule.currentQuestion--;
                ASTMED_LMS.QuizModule.showQuestion(ASTMED_LMS.QuizModule.currentQuestion);
            }
        },

        showQuestion: function(index) {
            $('.astmed-question').hide();
            $(`.astmed-question[data-question="${index}"]`).show();
            
            $('#current-question').text(index + 1);
            
            // Navigation butonları
            $('.astmed-quiz-prev').prop('disabled', index === 0);
            
            const totalQuestions = $('.astmed-question').length;
            if (index === totalQuestions - 1) {
                $('.astmed-quiz-next').hide();
                $('.astmed-quiz-submit').show();
            } else {
                $('.astmed-quiz-next').show();
                $('.astmed-quiz-submit').hide();
            }

            // Scroll to top
            $('.astmed-quiz-container').get(0).scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        },

        submitQuiz: async function() {
            // Son kontrol
            const totalQuestions = $('.astmed-question').length;
            const answeredQuestions = Object.keys(ASTMED_LMS.QuizModule.answers).length;
            
            if (answeredQuestions < totalQuestions) {
                const unanswered = totalQuestions - answeredQuestions;
                const confirmed = confirm(`${unanswered} soru cevaplanmadı. Yine de göndermek istiyor musunuz?`);
                if (!confirmed) return;
            }

            const $btn = $('.astmed-quiz-submit');
            $btn.prop('disabled', true).text('Gönderiliyor...');

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_submit_quiz',
                    quiz_id: $('.astmed-quiz-container').data('quiz-id'),
                    answers: ASTMED_LMS.QuizModule.answers,
                    time_taken: $('#quiz-timer').data('start-time') - ASTMED_LMS.QuizModule.timeRemaining,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    clearInterval(ASTMED_LMS.QuizModule.timer);
                    ASTMED_LMS.QuizModule.showResults(response.data);
                    ASTMED_LMS.trackEvent('quiz_completed', { 
                        quiz_id: $('.astmed-quiz-container').data('quiz-id'),
                        score: response.data.score 
                    });
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show('Quiz gönderilemedi', 'error');
                $btn.prop('disabled', false).text('Quiz\'i Tamamla');
            }
        },

        showResults: function(results) {
            const $container = $('.astmed-quiz-container');
            const passed = results.percentage >= results.pass_percentage;
            
            let html = `
                <div class="astmed-quiz-results">
                    <div class="astmed-results-header ${passed ? 'passed' : 'failed'}">
                        <h2>${passed ? 'Tebrikler! Başarılı oldunuz!' : 'Maalesef başarısız oldunuz'}</h2>
                        <div class="astmed-score-display">
                            <div class="score-circle">
                                <span class="score-number">${results.percentage}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="astmed-results-details">
                        <div class="astmed-result-item">
                            <span class="label">Toplam Soru:</span>
                            <span class="value">${results.total_questions}</span>
                        </div>
                        <div class="astmed-result-item">
                            <span class="label">Doğru Cevap:</span>
                            <span class="value">${results.correct_answers}</span>
                        </div>
                        <div class="astmed-result-item">
                            <span class="label">Yanlış Cevap:</span>
                            <span class="value">${results.incorrect_answers}</span>
                        </div>
                        <div class="astmed-result-item">
                            <span class="label">Geçme Notu:</span>
                            <span class="value">%${results.pass_percentage}</span>
                        </div>
                        <div class="astmed-result-item">
                            <span class="label">Süre:</span>
                            <span class="value">${ASTMED_LMS.utils.formatTime(results.time_taken)}</span>
                        </div>
                    </div>
            `;

            if (results.show_correct_answers) {
                html += '<div class="astmed-correct-answers">';
                html += '<h3>Doğru Cevaplar:</h3>';
                results.questions_review.forEach((question, index) => {
                    const isCorrect = question.user_answer === question.correct_answer;
                    html += `
                        <div class="astmed-answer-review ${isCorrect ? 'correct' : 'incorrect'}">
                            <div class="question-text">${index + 1}. ${question.question}</div>
                            <div class="answer-comparison">
                                <div class="user-answer">Sizin Cevabınız: ${question.user_answer || 'Cevaplanmadı'}</div>
                                <div class="correct-answer">Doğru Cevap: ${question.correct_answer}</div>
                                ${question.explanation ? `<div class="explanation">${question.explanation}</div>` : ''}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += `
                    <div class="astmed-results-actions">
                        <button class="astmed-lms-button astmed-lms-button-primary" onclick="window.location.reload()">
                            Kursa Dön
                        </button>
            `;

            if (results.can_retake) {
                html += `
                    <button class="astmed-lms-button astmed-lms-button-outline astmed-quiz-retake">
                        Tekrar Dene (${results.attempts_remaining} hak kaldı)
                    </button>
                `;
            }

            html += `
                    </div>
                </div>
            `;

            $container.html(html);
        },

        retakeQuiz: function() {
            window.location.reload();
        },

        saveProgress: function() {
            // Local storage'a progress kaydet
            const quizId = $('.astmed-quiz-container').data('quiz-id');
            const progressData = {
                currentQuestion: this.currentQuestion,
                answers: this.answers,
                timeRemaining: this.timeRemaining
            };
            
            localStorage.setItem(`astmed_quiz_${quizId}`, JSON.stringify(progressData));
        },

        loadProgress: function() {
            const quizId = $('.astmed-quiz-container').data('quiz-id');
            if (!quizId) return;

            const saved = localStorage.getItem(`astmed_quiz_${quizId}`);
            if (saved) {
                const progressData = JSON.parse(saved);
                this.currentQuestion = progressData.currentQuestion || 0;
                this.answers = progressData.answers || {};
                this.timeRemaining = progressData.timeRemaining || 0;
            }
        },

        adjustTimer: function() {
            // Responsive timer adjustments
            const $timer = $('#quiz-timer');
            if ($(window).width() < 768) {
                $timer.addClass('mobile');
            } else {
                $timer.removeClass('mobile');
            }
        }
    };

    // Abonelik Modülü
    ASTMED_LMS.SubscriptionModule = {
        init: function() {
            this.bindEvents();
            this.initPlanComparison();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-subscribe-btn', this.handleSubscription);
            $(document).on('click', '.astmed-cancel-subscription', this.handleCancellation);
            $(document).on('click', '.astmed-reactivate-subscription', this.handleReactivation);
            $(document).on('change', '.astmed-billing-cycle', this.updatePricing);
        },

        handleSubscription: async function(e) {
            e.preventDefault();
            const $btn = $(this);
            const planId = $btn.data('plan-id');
            const planName = $btn.closest('.astmed-plan-card').find('.astmed-plan-name').text();

            // Onay modal'ı
            ASTMED_LMS.ModalModule.show({
                title: 'Abonelik Onayı',
                content: `${planName} planına abone olmak istediğinizden emin misiniz?`,
                actions: [
                    {
                        text: 'İptal',
                        class: 'astmed-lms-button-outline',
                        action: () => ASTMED_LMS.ModalModule.hide()
                    },
                    {
                        text: 'Abone Ol',
                        class: 'astmed-lms-button-primary',
                        action: () => {
                            ASTMED_LMS.ModalModule.hide();
                            ASTMED_LMS.SubscriptionModule.processSubscription(planId);
                        }
                    }
                ]
            });
        },

        processSubscription: async function(planId) {
            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_subscribe',
                    plan_id: planId,
                    payment_method: 'stripe', // Default
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    ASTMED_LMS.NotificationModule.show(response.data.message, 'success');
                    ASTMED_LMS.trackEvent('subscription_started', { plan_id: planId });
                    
                    // Ödeme sayfasına yönlendir
                    if (response.data.payment_url) {
                        window.location.href = response.data.payment_url;
                    } else {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show(error.message, 'error');
            }
        },

        handleCancellation: function(e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');

            ASTMED_LMS.ModalModule.show({
                title: 'Abonelik İptali',
                content: `
                    <p>Aboneliğinizi iptal etmek istediğinizden emin misiniz?</p>
                    <div class="astmed-cancellation-options">
                        <label>
                            <input type="radio" name="cancel_type" value="end_of_period" checked />
                            Dönem sonunda iptal et (önerilen)
                        </label>
                        <label>
                            <input type="radio" name="cancel_type" value="immediate" />
                            Hemen iptal et
                        </label>
                    </div>
                    <div class="astmed-form-group">
                        <label>İptal Sebebi (opsiyonel):</label>
                        <textarea id="cancel-reason" placeholder="Neden iptal ediyorsunuz?"></textarea>
                    </div>
                `,
                actions: [
                    {
                        text: 'Vazgeç',
                        class: 'astmed-lms-button-outline',
                        action: () => ASTMED_LMS.ModalModule.hide()
                    },
                    {
                        text: 'İptal Et',
                        class: 'astmed-lms-button-danger',
                        action: () => {
                            const immediate = $('input[name="cancel_type"]:checked').val() === 'immediate';
                            const reason = $('#cancel-reason').val();
                            ASTMED_LMS.SubscriptionModule.processCancellation(subscriptionId, immediate, reason);
                        }
                    }
                ]
            });
        },

        processCancellation: async function(subscriptionId, immediate, reason) {
            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_cancel_subscription',
                    subscription_id: subscriptionId,
                    immediate: immediate,
                    reason: reason,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    ASTMED_LMS.ModalModule.hide();
                    ASTMED_LMS.NotificationModule.show(response.data.message, 'success');
                    ASTMED_LMS.trackEvent('subscription_cancelled', { 
                        subscription_id: subscriptionId,
                        immediate: immediate 
                    });
                    
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show(error.message, 'error');
            }
        },

        handleReactivation: async function(e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_reactivate_subscription',
                    subscription_id: subscriptionId,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    ASTMED_LMS.NotificationModule.show(response.data.message, 'success');
                    ASTMED_LMS.trackEvent('subscription_reactivated', { subscription_id: subscriptionId });
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show(error.message, 'error');
            }
        },

        updatePricing: function() {
            const cycle = $(this).val();
            $('.astmed-plan-card').each(function() {
                const $card = $(this);
                const monthlyPrice = $card.data('monthly-price');
                const yearlyPrice = $card.data('yearly-price');
                
                let price, savings;
                if (cycle === 'yearly') {
                    price = yearlyPrice;
                    savings = (monthlyPrice * 12) - yearlyPrice;
                } else {
                    price = monthlyPrice;
                    savings = 0;
                }
                
                $card.find('.astmed-plan-price').text(ASTMED_LMS.utils.formatPrice(price));
                
                if (savings > 0) {
                    $card.find('.astmed-plan-savings').text(`${ASTMED_LMS.utils.formatPrice(savings)} tasarruf!`).show();
                } else {
                    $card.find('.astmed-plan-savings').hide();
                }
            });
        },

        initPlanComparison: function() {
            // Plan özelliklerini karşılaştırma tablosu
            $('.astmed-compare-plans').on('click', function() {
                // Plan karşılaştırma modal'ı göster
                ASTMED_LMS.ModalModule.show({
                    title: 'Plan Karşılaştırması',
                    content: ASTMED_LMS.SubscriptionModule.generateComparisonTable(),
                    size: 'large'
                });
            });
        },

        generateComparisonTable: function() {
            // Bu fonksiyon plan karşılaştırma tablosunu generate eder
            return `
                <div class="astmed-plan-comparison">
                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>Özellik</th>
                                <th>Temel</th>
                                <th>Premium</th>
                                <th>Pro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Tüm Kurslara Erişim</td>
                                <td>✓</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Sertifika</td>
                                <td>✗</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Canlı Webinarlar</td>
                                <td>✗</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>1:1 Danışmanlık</td>
                                <td>✗</td>
                                <td>✗</td>
                                <td>✓</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        }
    };

    // Progress Modülü
    ASTMED_LMS.ProgressModule = {
        init: function() {
            this.updateProgressBars();
            this.trackVideoProgress();
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-mark-complete', this.markComplete);
            $(document).on('click', '.astmed-continue-learning', this.continueFromLastPosition);
        },

        updateProgressBars: function() {
            $('.astmed-progress-bar').each(function() {
                const $bar = $(this);
                const percentage = $bar.data('progress');
                const $fill = $bar.find('.astmed-progress-fill');
                
                // Animate progress bar
                setTimeout(() => {
                    $fill.css('width', percentage + '%');
                }, 100);
            });
        },

        trackVideoProgress: function() {
            // Video progress tracking
            $('video, iframe').each(function() {
                if (this.tagName === 'VIDEO') {
                    this.addEventListener('timeupdate', ASTMED_LMS.ProgressModule.updateVideoProgress);
                    this.addEventListener('ended', ASTMED_LMS.ProgressModule.videoCompleted);
                }
            });
        },

        updateVideoProgress: ASTMED_LMS.utils.throttle(function() {
            const video = this;
            const currentTime = video.currentTime;
            const duration = video.duration;
            const percentage = (currentTime / duration) * 100;
            const lessonId = $(video).closest('.astmed-lesson-content').data('lesson-id');

            // Progress'i server'a gönder
            $.post(astmed_lms_ajax.ajax_url, {
                action: 'astmed_update_lesson_progress',
                lesson_id: lessonId,
                current_time: currentTime,
                duration: duration,
                percentage: percentage,
                nonce: astmed_lms_ajax.nonce
            });
        }, 5000), // 5 saniyede bir güncelle

        videoCompleted: function() {
            const lessonId = $(this).closest('.astmed-lesson-content').data('lesson-id');
            
            // Video tamamlandı olarak işaretle
            $.post(astmed_lms_ajax.ajax_url, {
                action: 'astmed_complete_lesson',
                lesson_id: lessonId,
                nonce: astmed_lms_ajax.nonce
            }).done(function(response) {
                if (response.success) {
                    ASTMED_LMS.NotificationModule.show('Ders tamamlandı!', 'success');
                    ASTMED_LMS.trackEvent('lesson_completed', { lesson_id: lessonId });
                    
                    // Progress bar'ı güncelle
                    ASTMED_LMS.ProgressModule.updateProgressBars();
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show('Bir hata oluştu', 'error');
            }
        },

        continueFromLastPosition: function(e) {
            e.preventDefault();
            const lastPosition = $(this).data('last-position');
            const $video = $('video').first();
            
            if ($video.length && lastPosition) {
                $video[0].currentTime = lastPosition;
                $video[0].play();
            }
        }
    };

    // Bildirim Modülü
    ASTMED_LMS.NotificationModule = {
        queue: [],
        current: null,

        init: function() {
            this.bindEvents();
            this.loadUnreadNotifications();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-notification-close', this.hide);
            $(document).on('click', '.astmed-notification-item', this.markAsRead);
        },

        show: function(message, type = 'info', duration = 5000) {
            const notification = {
                id: Date.now(),
                message: message,
                type: type,
                duration: duration
            };

            this.queue.push(notification);
            this.processQueue();
        },

        processQueue: function() {
            if (this.current || this.queue.length === 0) return;

            const notification = this.queue.shift();
            this.current = notification;
            this.render(notification);
        },

        render: function(notification) {
            const typeIcons = {
                success: '✓',
                error: '✗',
                warning: '⚠',
                info: 'ℹ'
            };

            const html = `
                <div class="astmed-notification astmed-notification-${notification.type}" 
                     data-notification-id="${notification.id}">
                    <div class="astmed-notification-header">
                        <div class="astmed-notification-icon">${typeIcons[notification.type]}</div>
                        <button class="astmed-notification-close">×</button>
                    </div>
                    <div class="astmed-notification-body">
                        ${notification.message}
                    </div>
                </div>
            `;

            $('body').append(html);
            
            const $notification = $(`.astmed-notification[data-notification-id="${notification.id}"]`);
            
            // Fade in
            setTimeout(() => $notification.addClass('show'), 100);

            // Auto hide
            if (notification.duration > 0) {
                setTimeout(() => {
                    this.hide($notification);
                }, notification.duration);
            }
        },

        hide: function(target) {
            let $notification;
            
            if (target && target.target) {
                // Event'ten geldi
                $notification = $(target.target).closest('.astmed-notification');
            } else if (target && target.jquery) {
                // jQuery object
                $notification = target;
            } else {
                // En son gösterilen
                $notification = $('.astmed-notification').last();
            }

            if ($notification.length) {
                $notification.removeClass('show');
                setTimeout(() => {
                    $notification.remove();
                    ASTMED_LMS.NotificationModule.current = null;
                    ASTMED_LMS.NotificationModule.processQueue();
                }, 300);
            }
        },

        loadUnreadNotifications: async function() {
            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_get_notifications',
                    unread_only: true,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success && response.data.length > 0) {
                    this.updateNotificationBadge(response.data.length);
                    this.renderNotificationDropdown(response.data);
                }
            } catch (error) {
                console.error('Bildirimler yüklenemedi:', error);
            }
        },

        updateNotificationBadge: function(count) {
            $('.astmed-notification-badge').text(count).toggle(count > 0);
        },

        renderNotificationDropdown: function(notifications) {
            const $dropdown = $('.astmed-notifications-dropdown');
            if (!$dropdown.length) return;

            let html = '';
            notifications.forEach(notification => {
                html += `
                    <div class="astmed-notification-item" data-id="${notification.id}">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${notification.created_at}</div>
                    </div>
                `;
            });

            $dropdown.html(html);
        },

        markAsRead: async function() {
            const notificationId = $(this).data('id');
            
            try {
                await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_mark_notification_read',
                    notification_id: notificationId,
                    nonce: astmed_lms_ajax.nonce
                });

                $(this).addClass('read');
                ASTMED_LMS.NotificationModule.updateNotificationBadge(
                    $('.astmed-notification-item:not(.read)').length
                );
            } catch (error) {
                console.error('Bildirim okundu olarak işaretlenemedi:', error);
            }
        }
    };

    // Modal Modülü
    ASTMED_LMS.ModalModule = {
        current: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-modal-overlay', this.handleOverlayClick);
            $(document).on('click', '.astmed-modal-close', this.hide);
            $(document).on('keydown', this.handleKeydown);
        },

        show: function(options) {
            const defaults = {
                title: '',
                content: '',
                actions: [],
                size: 'medium',
                closable: true
            };

            const config = $.extend({}, defaults, options);
            this.current = config;

            this.render(config);
        },

        render: function(config) {
            let actionsHtml = '';
            if (config.actions.length > 0) {
                actionsHtml = '<div class="astmed-modal-footer">';
                config.actions.forEach(action => {
                    actionsHtml += `
                        <button class="astmed-lms-button ${action.class || 'astmed-lms-button-primary'}" 
                                data-action="${config.actions.indexOf(action)}">
                            ${action.text}
                        </button>
                    `;
                });
                actionsHtml += '</div>';
            }

            const html = `
                <div class="astmed-modal-overlay">
                    <div class="astmed-modal astmed-modal-${config.size}">
                        <div class="astmed-modal-header">
                            <h3 class="astmed-modal-title">${config.title}</h3>
                            ${config.closable ? '<button class="astmed-modal-close">×</button>' : ''}
                        </div>
                        <div class="astmed-modal-body">
                            ${config.content}
                        </div>
                        ${actionsHtml}
                    </div>
                </div>
            `;

            $('body').append(html).addClass('astmed-modal-open');

            // Action button events
            $('.astmed-modal').on('click', 'button[data-action]', function() {
                const actionIndex = $(this).data('action');
                const action = config.actions[actionIndex];
                if (action && action.action) {
                    action.action();
                }
            });

            // Focus trap
            setTimeout(() => {
                $('.astmed-modal').find('button, input, select, textarea').first().focus();
            }, 100);
        },

        hide: function() {
            $('.astmed-modal-overlay').fadeOut(200, function() {
                $(this).remove();
                $('body').removeClass('astmed-modal-open');
                ASTMED_LMS.ModalModule.current = null;
            });
        },

        handleOverlayClick: function(e) {
            if (e.target === this) {
                ASTMED_LMS.ModalModule.hide();
            }
        },

        handleKeydown: function(e) {
            if (e.keyCode === 27 && ASTMED_LMS.ModalModule.current) { // ESC
                ASTMED_LMS.ModalModule.hide();
            }
        }
    };

    // Form Modülü
    ASTMED_LMS.FormModule = {
        init: function() {
            this.bindEvents();
            this.initValidation();
        },

        bindEvents: function() {
            $(document).on('submit', '.astmed-form', this.handleSubmit);
            $(document).on('input change', '.astmed-form-input', this.validateField);
            $(document).on('click', '.astmed-form-toggle-password', this.togglePassword);
        },

        handleSubmit: async function(e) {
            e.preventDefault();
            const $form = $(this);
            
            if (!ASTMED_LMS.FormModule.validateForm($form)) {
                return false;
            }

            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('İşleniyor...');

            try {
                const formData = new FormData(this);
                formData.append('action', $form.data('action'));
                formData.append('nonce', astmed_lms_ajax.nonce);

                const response = await $.post(astmed_lms_ajax.ajax_url, formData, {
                    processData: false,
                    contentType: false
                });

                if (response.success) {
                    ASTMED_LMS.NotificationModule.show(response.data.message, 'success');
                    
                    if (response.data.redirect) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else if (response.data.reload) {
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show(error.message, 'error');
            } finally {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        },

        validateForm: function($form) {
            let isValid = true;
            
            $form.find('.astmed-form-input[required]').each(function() {
                if (!ASTMED_LMS.FormModule.validateField.call(this)) {
                    isValid = false;
                }
            });

            return isValid;
        },

        validateField: function() {
            const $field = $(this);
            const value = $field.val().trim();
            const type = $field.attr('type');
            const required = $field.prop('required');
            
            let isValid = true;
            let errorMessage = '';

            // Required validation
            if (required && !value) {
                isValid = false;
                errorMessage = 'Bu alan zorunludur.';
            }
            // Email validation
            else if (type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Geçerli bir e-posta adresi girin.';
                }
            }
            // Password validation
            else if (type === 'password' && value) {
                if (value.length < 8) {
                    isValid = false;
                    errorMessage = 'Şifre en az 8 karakter olmalıdır.';
                }
            }
            // Confirm password validation
            else if ($field.data('confirm-password')) {
                const originalPassword = $($field.data('confirm-password')).val();
                if (value !== originalPassword) {
                    isValid = false;
                    errorMessage = 'Şifreler eşleşmiyor.';
                }
            }

            // UI feedback
            $field.removeClass('error success');
            $field.siblings('.astmed-form-error').remove();

            if (!isValid && errorMessage) {
                $field.addClass('error');
                $field.after(`<span class="astmed-form-error">${errorMessage}</span>`);
            } else if (value) {
                $field.addClass('success');
            }

            return isValid;
        },

        togglePassword: function() {
            const $input = $($(this).data('target'));
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            
            $input.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        },

        initValidation: function() {
            // Real-time validation for better UX
            $('.astmed-form-input').on('blur', function() {
                ASTMED_LMS.FormModule.validateField.call(this);
            });
        }
    };

    // Sertifika Modülü
    ASTMED_LMS.CertificateModule = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.astmed-download-certificate', this.downloadCertificate);
            $(document).on('click', '.astmed-share-certificate', this.shareCertificate);
            $(document).on('click', '.astmed-verify-certificate', this.verifyCertificate);
        },

        downloadCertificate: function(e) {
            e.preventDefault();
            const courseId = $(this).data('course-id');
            const downloadUrl = $(this).attr('href');
            
            // Download tracking
            ASTMED_LMS.trackEvent('certificate_downloaded', { course_id: courseId });
            
            // Open in new tab
            window.open(downloadUrl, '_blank');
        },

        shareCertificate: function(e) {
            e.preventDefault();
            const certificateUrl = $(this).data('certificate-url');
            const courseName = $(this).data('course-name');
            
            if (navigator.share) {
                navigator.share({
                    title: `${courseName} Sertifikası`,
                    text: `${courseName} kursunu başarıyla tamamladım!`,
                    url: certificateUrl
                });
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(certificateUrl).then(() => {
                    ASTMED_LMS.NotificationModule.show('Sertifika linki kopyalandı!', 'success');
                });
            }
        },

        verifyCertificate: async function(e) {
            e.preventDefault();
            const certificateNumber = $(this).data('certificate-number');
            
            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_verify_certificate',
                    certificate_number: certificateNumber,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    const data = response.data;
                    ASTMED_LMS.ModalModule.show({
                        title: 'Sertifika Doğrulama',
                        content: `
                            <div class="certificate-verification">
                                <div class="verification-status success">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>Sertifika Geçerli</h3>
                                </div>
                                <div class="certificate-details">
                                    <p><strong>Sertifika No:</strong> ${data.certificate_number}</p>
                                    <p><strong>Kursiyerin Adı:</strong> ${data.student_name}</p>
                                    <p><strong>Kurs:</strong> ${data.course_name}</p>
                                    <p><strong>Tamamlanma Tarihi:</strong> ${data.completion_date}</p>
                                    <p><strong>Verilme Tarihi:</strong> ${data.issue_date}</p>
                                </div>
                            </div>
                        `,
                        actions: [
                            {
                                text: 'Kapat',
                                class: 'astmed-lms-button-primary',
                                action: () => ASTMED_LMS.ModalModule.hide()
                            }
                        ]
                    });
                } else {
                    ASTMED_LMS.ModalModule.show({
                        title: 'Sertifika Doğrulama',
                        content: `
                            <div class="certificate-verification">
                                <div class="verification-status error">
                                    <i class="fas fa-times-circle"></i>
                                    <h3>Sertifika Geçersiz</h3>
                                    <p>Bu sertifika numarası sistemde bulunamadı.</p>
                                </div>
                            </div>
                        `,
                        actions: [
                            {
                                text: 'Kapat',
                                class: 'astmed-lms-button-primary',
                                action: () => ASTMED_LMS.ModalModule.hide()
                            }
                        ]
                    });
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show('Doğrulama yapılamadı', 'error');
            }
        }
    };

    // Arama Modülü
    ASTMED_LMS.SearchModule = {
        init: function() {
            this.bindEvents();
            this.initAutocomplete();
        },

        bindEvents: function() {
            $(document).on('input', '.astmed-search-input', 
                ASTMED_LMS.utils.debounce(this.handleSearch, 300)
            );
            $(document).on('click', '.astmed-search-clear', this.clearSearch);
        },

        handleSearch: async function() {
            const query = $(this).val().trim();
            const $results = $('.astmed-search-results');
            
            if (query.length < 2) {
                $results.hide();
                return;
            }

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_search',
                    query: query,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    ASTMED_LMS.SearchModule.renderResults(response.data, $results);
                }
            } catch (error) {
                console.error('Arama hatası:', error);
            }
        },

        renderResults: function(results, $container) {
            let html = '';
            
            if (results.length === 0) {
                html = '<div class="search-no-results">Sonuç bulunamadı</div>';
            } else {
                results.forEach(item => {
                    html += `
                        <div class="search-result-item" data-type="${item.type}">
                            <h4><a href="${item.url}">${item.title}</a></h4>
                            <p>${item.excerpt}</p>
                            <span class="result-type">${item.type_label}</span>
                        </div>
                    `;
                });
            }
            
            $container.html(html).show();
        },

        clearSearch: function() {
            $('.astmed-search-input').val('');
            $('.astmed-search-results').hide();
        },

        initAutocomplete: function() {
            // Simple autocomplete implementation
            $('.astmed-search-input').attr('autocomplete', 'off');
        }
    };

    // Ana başlatma
    $(document).ready(function() {
        ASTMED_LMS.init();
    });

    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error:', e.error);
        
        // Kritik hataları bildir
        if (e.error && e.error.stack) {
            $.post(astmed_lms_ajax.ajax_url, {
                action: 'astmed_log_js_error',
                error: e.error.message,
                stack: e.error.stack,
                url: window.location.href,
                nonce: astmed_lms_ajax.nonce
            });
        }
    });

    // Service Worker registration (PWA support)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js')
                .then(function(registration) {
                    console.log('SW registered: ', registration);
                })
                .catch(function(registrationError) {
                    console.log('SW registration failed: ', registrationError);
                });
        });
    }

})(jQuery);!', 'success');
                    $('.astmed-lesson-status').addClass('completed');
                }
            });
        },

        markComplete: async function(e) {
            e.preventDefault();
            const $btn = $(this);
            const lessonId = $btn.data('lesson-id');

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_complete_lesson',
                    lesson_id: lessonId,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    $btn.text('Tamamlandı').prop('disabled', true)
                        .removeClass('astmed-lms-button-primary')
                        .addClass('astmed-lms-button-success');
                    
                    ASTMED_LMS.NotificationModule.show('Ders tamamlandı/**
 * ASTMED LMS Frontend JavaScript
 * Modern ES6+ kod, async/await, modüler yapı
 */

(function($) {
    'use strict';

    // Ana ASTMED LMS nesnesi
    window.ASTMED_LMS = {
        init: function() {
            this.bindEvents();
            this.initModules();
            this.setupAjax();
            console.log('ASTMED LMS Frontend Initialized');
        },

        // Event listeners
        bindEvents: function() {
            $(document).ready(() => {
                this.onDocumentReady();
            });

            $(window).on('load', () => {
                this.onWindowLoad();
            });

            $(window).on('resize', () => {
                this.onWindowResize();
            });
        },

        // Modülleri başlat
        initModules: function() {
            this.CourseModule.init();
            this.QuizModule.init();
            this.SubscriptionModule.init();
            this.ProgressModule.init();
            this.NotificationModule.init();
            this.ModalModule.init();
            this.FormModule.init();
        },

        // AJAX setup
        setupAjax: function() {
            $.ajaxSetup({
                url: astmed_lms_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', astmed_lms_ajax.nonce);
                }
            });

            // Global AJAX error handler
            $(document).ajaxError((event, xhr, settings, error) => {
                console.error('AJAX Error:', error);
                this.NotificationModule.show('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            });
        },

        // Document ready
        onDocumentReady: function() {
            this.setupSmoothScroll();
            this.setupLazyLoading();
            this.setupTooltips();
            $('.astmed-fade-in').each((i, el) => {
                setTimeout(() => $(el).addClass('visible'), i * 100);
            });
        },

        // Window load
        onWindowLoad: function() {
            this.hideLoadingSpinners();
            this.initAnalytics();
        },

        // Window resize
        onWindowResize: function() {
            this.CourseModule.adjustLayout();
            this.QuizModule.adjustTimer();
        },

        // Smooth scroll setup
        setupSmoothScroll: function() {
            $('a[href*="#"]:not([href="#"])').click(function() {
                if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && 
                    location.hostname === this.hostname) {
                    const target = $(this.hash);
                    if (target.length) {
                        $('html, body').animate({
                            scrollTop: target.offset().top - 80
                        }, 800);
                        return false;
                    }
                }
            });
        },

        // Lazy loading setup
        setupLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const lazyImages = document.querySelectorAll('img[data-lazy]');
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.lazy;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                lazyImages.forEach(img => imageObserver.observe(img));
            }
        },

        // Tooltips setup
        setupTooltips: function() {
            $('[data-tooltip]').each(function() {
                const $el = $(this);
                const text = $el.data('tooltip');
                
                $el.hover(
                    function() {
                        const tooltip = $('<div class="astmed-tooltip">' + text + '</div>');
                        $('body').append(tooltip);
                        
                        const pos = $el.offset();
                        tooltip.css({
                            position: 'absolute',
                            top: pos.top - tooltip.outerHeight() - 10,
                            left: pos.left + ($el.outerWidth() - tooltip.outerWidth()) / 2,
                            zIndex: 9999
                        });
                    },
                    function() {
                        $('.astmed-tooltip').remove();
                    }
                );
            });
        },

        // Loading spinners gizle
        hideLoadingSpinners: function() {
            $('.astmed-loading').fadeOut();
        },

        // Analytics başlat
        initAnalytics: function() {
            // Page view tracking
            this.trackEvent('page_view', {
                page: window.location.pathname,
                title: document.title
            });
        },

        // Event tracking
        trackEvent: function(event, data = {}) {
            if (typeof gtag !== 'undefined') {
                gtag('event', event, data);
            }
            
            // Custom analytics
            $.post(astmed_lms_ajax.ajax_url, {
                action: 'astmed_track_event',
                event_type: event,
                event_data: data,
                nonce: astmed_lms_ajax.nonce
            });
        },

        // Utility fonksiyonlar
        utils: {
            formatPrice: function(price, currency = '₺') {
                return currency + parseFloat(price).toFixed(2);
            },

            formatTime: function(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;

                if (hours > 0) {
                    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }
                return `${minutes}:${secs.toString().padStart(2, '0')}`;
            },

            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },

            throttle: function(func, limit) {
                let inThrottle;
                return function() {
                    const args = arguments;
                    const context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(() => inThrottle = false, limit);
                    }
                };
            }
        }
    };

    // Kurs Modülü
    ASTMED_LMS.CourseModule = {
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initFavorites();
        },

        bindEvents: function() {
            // Kurs kartlarına hover efekti
            $('.astmed-course-card').hover(
                function() { $(this).addClass('hovered'); },
                function() { $(this).removeClass('hovered'); }
            );

            // Kurs enrollment
            $(document).on('click', '.astmed-enroll-course', this.handleEnrollment);

            // Kurs filtreleme
            $(document).on('change', '.astmed-course-filter', this.handleFilter);

            // Kurs arama
            $(document).on('input', '.astmed-course-search', 
                ASTMED_LMS.utils.debounce(this.handleSearch, 300)
            );
        },

        handleEnrollment: async function(e) {
            e.preventDefault();
            const $btn = $(this);
            const courseId = $btn.data('course-id');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('İşleniyor...');

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_enroll_course',
                    course_id: courseId,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    $btn.text('Kayıt Tamamlandı').removeClass('astmed-lms-button-primary')
                        .addClass('astmed-lms-button-success');
                    
                    ASTMED_LMS.NotificationModule.show(response.data.message, 'success');
                    ASTMED_LMS.trackEvent('course_enrollment', { course_id: courseId });
                    
                    // Sayfayı yenile (ders listesi güncellensin)
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                ASTMED_LMS.NotificationModule.show(error.message, 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        },

        handleFilter: function() {
            const $container = $('.astmed-courses-grid');
            const filters = {};

            $('.astmed-course-filter').each(function() {
                const key = $(this).data('filter');
                const value = $(this).val();
                if (value) filters[key] = value;
            });

            ASTMED_LMS.CourseModule.filterCourses(filters);
        },

        handleSearch: function() {
            const query = $(this).val().toLowerCase();
            $('.astmed-course-card').each(function() {
                const $card = $(this);
                const title = $card.find('.astmed-course-title').text().toLowerCase();
                const excerpt = $card.find('.astmed-course-excerpt').text().toLowerCase();
                
                if (title.includes(query) || excerpt.includes(query)) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        },

        filterCourses: function(filters) {
            $('.astmed-course-card').each(function() {
                const $card = $(this);
                let show = true;

                Object.keys(filters).forEach(key => {
                    const cardValue = $card.data(key);
                    if (cardValue !== filters[key]) {
                        show = false;
                    }
                });

                if (show) {
                    $card.fadeIn();
                } else {
                    $card.fadeOut();
                }
            });
        },

        initFilters: function() {
            // URL'den filtreleri al
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach((value, key) => {
                $(`.astmed-course-filter[data-filter="${key}"]`).val(value);
            });

            this.handleFilter();
        },

        initFavorites: function() {
            $(document).on('click', '.astmed-favorite-course', this.toggleFavorite);
        },

        toggleFavorite: async function(e) {
            e.preventDefault();
            const $btn = $(this);
            const courseId = $btn.data('course-id');
            const isFavorited = $btn.hasClass('favorited');

            try {
                const response = await $.post(astmed_lms_ajax.ajax_url, {
                    action: 'astmed_toggle_favorite',
                    course_id: courseId,
                    nonce: astmed_lms_ajax.nonce
                });

                if (response.success) {
                    $btn.toggleClass('favorited');