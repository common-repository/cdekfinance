<?php
    if ( ! defined( 'ABSPATH' ) ) exit;
    /**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://cdekpay.ru
 * @since      1.0.0
 *
 * @package    Cdekpay
 * @subpackage Cdekpay/admin/partials
 */
?>

<div class="wrap cdekpay_reference">
    <h2><?php esc_html__( 'CdekPay Reference', 'cdekpay' ); ?></h2>
    <a href="https://cdekpay.ru/" target="_blank">
        <img style="width: 200px; height: auto;" src="/wp-content/plugins/cdekpay/admin/images/cdekpay.png" alt="СДЭК Pay">
    </a>

    <h2>Подключение</h2>
    <ol>
        <li>Перейдите на сайт <a href="https://cdekpay.ru/" target="_blank">https://cdekpay.ru/</a> и оставьте заявку на подключение.</li>
        <li>После обсуждения необходимых действий с менеджером и активации необходимых платежных
            систем (банковская карта и оплата по qr-коду через СБП) у вас появится
            доступ к личному кабинету СДЭК Pay (<a href="https://secure.cdekfin.ru/login" target="_blank">https://secure.cdekfin.ru/login</a>).
        </li>
    </ol>

    <h2>Настройка модуля</h2>
    <ol>
        <li>Для настройки модуля в админ. панели перейдите на страницу: CDEKPAY -> Настройки.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-1.png" alt="рис.1" />
            В результате откроется страница настроек модуля во вкладке “Платежи”:
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-2.png" alt="рис.2" />
        </li>
        <li>Активируйте галочку "Включить платежный метод CDEK Pay", чтобы модуль стал активным на сайте.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-3.png" alt="рис.3" />
        </li>
        <li>Поля “Заголовок” и “Описание” будут заполнены по умолчанию. Вы можете внести другие значения в эти поля.
            Здесь задаются тексты, которые ваши пользователи увидят на экране оформления заказа при выборе способа оплаты:
            <img class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-4.png" alt="рис.4" />
        </li>
        <li>Заполните поле “Логин” (заполненное значение на скриншоте - пример).
            <img class="cdekpay_img_medium"src="/wp-content/plugins/cdekpay/admin/images/settings-5.png" alt="рис.5" />
            4.1. Для этого перейдите в ЛК CDEKPAY на страницу Настройки -> Редактировать магазин.
            4.2. Скопируйте значение из поля “Логин” и вставьте его в поле “Логин” в админ. панели вашего сайта.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-6.png" alt="рис.6" />
        </li>
        <li>Заполните поле “Secret Key” (заполненное значение на скриншоте - пример).
            <img style=""  class="cdekpay_img_medium"  src="/wp-content/plugins/cdekpay/admin/images/settings-7.png" alt="рис.7" />
            5.1. Для этого перейдите в ЛК CDEKPAY на страницу Интеграция -> Настройка API.
            5.2. Скопируйте значение из поля “Secret Key” и вставьте его в поле “Secret Key” в админ. панели вашего сайта. Если значение в поле в ЛК пустое, задайте его самостоятельно.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-8.png" alt="рис.8" />
        </li>
        <li>Заполните поле “Test Secret Key” (заполненное значение на скриншоте - пример).
            <img class="cdekpay_img_medium" style="" src="/wp-content/plugins/cdekpay/admin/images/settings-9.png" alt="рис.9" />
            6.1. Для этого вернитесь в ЛК CDEKPAY на ту же страницу Интеграция -> Настройка API.
            6.2. Скопируйте значение из поля “Test Secret Key” и вставьте его в поле “Test Secret Key” в админ. панели вашего сайта. Если значение в поле в ЛК пустое, задайте его самостоятельно.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-10.png" alt="рис.10" />
        </li>
        <li>Заполните поле "Аккаунты для тестовых платежей". Введите в это поле email пользователя-администратора, под которым была осуществлена авторизация в админ.
            панели сайта. Обратите внимание, что в тестовом режиме воспользоваться модулем будет возможно только при указании данного адреса в форме оформления заказа.
            Подробнее о тестовом режиме.
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-11.png" alt="рис.11" />
        </li>
        <li>Активируйте галочку “Тестовый режим платежей (Вкл/Выкл)” только если требуется проверка тестовых платежей.</li>
        <li>Нажмите на “Сохранить изменения”.
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-12.png" alt="рис.12" />
        </li>
        <li>Далее необходимо, наоборот, перенести некоторые данные из админ. панели вашего сайта в ЛК CDEKPAY. <br />
            10.1. Скопируйте значение из поля “URL для оповещения о платеже” в админ. панели.
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-13.png" alt="рис.13" />
            Далее вставьте скопированное значение в следующих полях в ЛК CDEKPAY:

            10.2. Интеграция -> Настройка API, поле “URL для оповещения о платеже”.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-14.png" alt="рис.14" />
            10.3. Интеграция -> Настройка API, поле “URL для оповещения о тестовом платеже”.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-15.png" alt="рис.15" />
            10.4. Нажмите на “Сохранить”.
        </li>
    </ol>


    <h2>Использование модуля</h2>
    <p>После установки и настройки модуля ваши пользователи увидят блок CDEK PAY на экране оформления заказа:
        <img class="cdekpay_img_medium" style="" src="/wp-content/plugins/cdekpay/admin/images/settings-16.png" alt="рис.16" />
    </p>
    <p>При выборе метода оплаты CDEK PAY ваши пользователи будут перенаправлены на экран платежного шлюза:
        <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-17.png" alt="рис.17" />
    </p>
    <p>Здесь пользователи могут выбрать оплату заказа через СБП или картой.<br />
        Далее, после оплаты, пользователи будут перенаправлены обратно на сайт на страницу с сообщением об успешной/неуспешной оплате.</p>


    <h2>Тестовый режим</h2>
    <p>Перед использованием модуля в боевом режиме с реальными пользователями вы можете протестировать его. Это позволит вам убедиться,
        что модуль установлен и настроен корректно и готов к работе. Тестовый режим предполагает использование модуля без осуществления оплаты.<br />
        Для того, чтобы включить тестовый режим, необходимо:
    </p>
    <ol>
        <li>В админ. панели зайти на страницу CDEKPAY -> Настройки
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-18.png" alt="рис.18" />
        </li>
        <li>Активировать галочку “Включить Тестовый режим платежей”
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-19.png" alt="рис.19" />
        </li>
        <li>Нажать на “Сохранить изменения”</li>
    </ol>
    <p><strong>Как происходит оформление заказа в тестовом режиме:</strong></p>
    <ol>
        <li>Добавьте товар в корзину и перейдите к экрану оформления заказа.<br />
            1.1. Важно: в поле Email введите тот же адрес почты, который введен в настройках модуля в поле “Аккаунты для тестовых платежей”
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-20.png" alt="рис.20" /><br />
            1.2. В списке доступных методов оплаты выберите CDEK PAY
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-21.png" alt="рис.21" /><br />
            1.3. Заполните остальные обязательные поля и нажмите на кнопку оформления заказа
            Вы будете перенаправлены на страницу следующего содержания:
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-22.png" alt="рис.22" />
        </li>
        <li>Чтобы протестировать поведение системы в случае успешной оплаты, нажмите на “Успешная оплата”. Вы будете перенаправлены на страницу вашего сайта, оповещающую об
            успешной оплате.<br />
            Убедитесь, что в списке заказов (WooCommerce -> Заказы) появилась запись об этом заказе, статус заказа - “Обработка”.
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-23.png" alt="рис.23" />
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-24.png" alt="рис.24" />
        </li>
        <li>Чтобы протестировать поведение системы в случае ошибки при оплате, нажмите на “Неуспешная оплата”. Вы будете перенаправлены на страницу вашего сайта, оповещающую об ошибке при оплате.</li>
    </ol>
    <p>После того, как тестирование будет завершено, боевой режим можно активировать, сняв галочку с поля “Включить Тестовый режим платежей”.</p>


    <h2>Возврат оплаты заказа</h2>
    <p>Модуль CDEK Pay также дает возможность осуществлять возврат средств.</p>
    <p><strong>Как осуществить возврат</strong><br />
        Возврат необходимо осуществить вручную через админ. панель WordPress вашего сайта.<br />
        Важно: прямое изменение статуса заказа на “Отменен” или “Возвращен” не приведет к автоматическому возврату средств вашему клиенту.
        Обязательно завершите процесс возврата, описанный здесь.<br />
        Также важно, что возврат можно осуществить только для заказов в статусе “Обработка”, т.е. только для оплаченных заказов.</p>
    <ol>
        <li>В админ. панели перейдите к списку заказов через левое боковое меню:
            WooCommerce -> Заказы
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-25.png" alt="рис.25"  />
        </li>
        <li>На открывшейся странице вы увидите список всех заказов на вашем сайте.
            Найдите нужный заказ, воспользовавшись поиском или просмотрев список. Далее нажмите на номер заказа, чтобы перейти к его карточке:
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-26.png" alt="рис.26"   />
        </li>
        <li>На карточке заказа найдите блок с информацией о товарах и оплате. Нажмите на “Возврат”:

        <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-27.png" alt="рис.27"  />
        <li>В открывшемся окне задайте сумму средств, которую необходимо вернуть: вы можете осуществить полный возврат всей суммы, или частичный возврат.
            Частичный возврат можно осуществить, заполнив либо:<br />
            4.1. Поле в столбце “Кол-во”, если необходимо вернуть оплату за определенные товары (в таком случае поле в столбце “Итого” и поле “Сумма возврата”
            рассчитаются и заполнятся автоматически):<br />
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-28.png" alt="рис.28"  /><br />
            4.2. Поле в столбце “Итого”, если необходимо вернуть определенную сумму (поле “Сумма возврата” заполнится автоматически):<br />
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-29.png" alt="рис.29"   />
            Вы также можете задать значение для поля в блоке “Доставка”, если необходимо вернуть часть этой суммы.<br />

        </li>
        <li>В случае, если было заполнено поле количества товаров (если был выбран шаг 4.1), вы увидите поле “Вернуть возврат в запас”:

            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-30.png" alt="рис.30"   /><br />
            Активируйте галочку, если необходимо пополнить запас товара, за оплату которого будет возвращена сумма.<br />
            Чтобы проверить запас товара, перейдите к карточке товара через левое боковое меню:
            Товары -> Все товары
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-31.png" alt="рис.31"    />
            В списке товаров нажмите на название нужного товара, чтобы перейти к его карточке:
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-32.png" alt="рис.32"    />
            В карточке найдите блок “Данные товара”. Откройте вкладку “Запасы”. В поле “Количество” будет отображено актуальное количество товара:<br />
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-33.png" alt="рис.33"     />
        </li>
        <li>Для осуществления возврата после заданных настроек нажмите на “Возврат <...> - CDEK PAY”
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-34.png" alt="рис.34"   />
        </li>
        <li>Подтвердите действие в подсказке от браузера:
            <img style="" class="cdekpay_img_medium" src="/wp-content/plugins/cdekpay/admin/images/settings-35.png" alt="рис.35"    />
            Процесс возврата запущен.
        </li>
    </ol>
    <h3> Сроки возврата средств</h3>
    <p>

        В случае оплаты заказа по СБП средства будут возвращены сразу после инициализации оплаты в админ. панели.<br />
        В случае оплаты картой - в течение 7 рабочих дней.<br />
        В отдельных случаях возможна задержка сроков на стороне банка.</p>


        <h3>Как проверить, что платеж был возвращен</h3>
        <p>Чтобы проверить, что процесс возврата был осуществлен корректно, обратите внимание на следующие признаки в админ. панели.<br />

        <strong>В случае полного возврата:</strong>

        </p>
    <ol>
        <li>
            На странице карточки заказа (WooCommerce -> Заказы -> Нужный заказ)
            в блоке “Примечания заказа” отображены сообщения:
            <ul>
                <li>Инициирован возврат платежа CDEK PAY: <...></li>
                <li>Статус заказа изменён с «Обработка» на «Возврат».</li>
                <li>Статус заказа изменён с «Обработка» на «Возвращён».</li>
            </ul>
            <img style="" class="cdekpay_img_small" src="/wp-content/plugins/cdekpay/admin/images/settings-36.png" alt="рис.36"    />
        </li>
        <li>
            В списке заказов (WooCommerce -> Заказы) статус заказа - “Возвращён”, в столбце “Итого” отображена зачеркнутая изначальная сумма и справа - сумма за вычетом возврата:
            <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-37.png" alt="рис.37"     />
        </li>
    </ol>

    <p><strong>В случае частичного возврата:</strong></p>
    <ol>
        <li>На странице карточки заказа (WooCommerce -> Заказы -> Нужный заказ)
            в блоке “Примечания заказа” отображены сообщения:
            <ul>
                <li>Инициирован возврат платежа CDEK PAY: <...></li>
                <li>Статус заказа изменён с «Обработка» на «Возврат».</li>
            </ul>
            <img style="" class="cdekpay_img_small"  src="/wp-content/plugins/cdekpay/admin/images/settings-38.png" alt="рис.38"     />
        </li>
        <li>В списке заказов (WooCommerce -> Заказы) статус заказа - “Обратока”, в столбце “Итого” отображена зачеркнутая изначальная сумма и справа - сумма за вычетом возврата:

        <img style="" src="/wp-content/plugins/cdekpay/admin/images/settings-39.png" alt="рис.39"      />
        </li>
    </ol>
</div>