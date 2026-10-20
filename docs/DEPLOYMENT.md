# CarAPI-ის გამოყოფილი PHP გარემო

2026-09-26-დან `car.socialsave.cc` საჯაროდ ჩართულია. სამივე დროშა FPM-ში 1-ია: `CARAPI_PLATFORM_ENABLED`, `CARAPI_CATALOG_ENABLED`, `CARAPI_WIDGET_ENABLED`. SMTP მიერთებულია. ანგარიშის მონაცემების ავტომატური სარეზერვო ასლი გამართული არ არის და აღდგენა არ არის გარანტირებული.

## რა დგას

- PHP 8.4: `/www/server/php/84/sbin/php-fpm`
- მომსახურება: `carapi-php-fpm.service` და `carapi-fpm-proxy.service` (systemd, boot-ზე ჩართული)
- კონფიგურაცია: `/www/server/php/carapi/php-fpm.conf` (root, რეჟიმი 600)
- პულის მომხმარებელი: `carapi`, login shell-ის გარეშე
- FPM socket: `/www/server/php/carapi/fpm.sock` მხოლოდ `carapi`-სთვის
- nginx-ის socket: `/www/server/php/carapi/nginx.sock`
- პროქსი `/www/server/php/carapi/peer-proxy.py` აკავშირებს მხოლოდ იმ პროცესს, რომლის executable არის `/www/server/nginx/sbin/nginx` და მშობელი მიმდინარე nginx master-ია
- სესიები: `/www/wwwroot/car.socialsave.cc/data/sessions`
- საიდუმლო წყარო: `/www/server/carapi/*.env`, public/-ისა და Git-ის გარეთ
- runtime იღებს `carapi_catalog_reader` და `carapi_platform_app` ანგარიშებს. მიგრაციის ადმინისტრატორი პულში არ არის
- `clear_env = yes`. ცვლადები პულში აშკარადაა ჩამოთვლილი

nginx და სხვა საიტების PHP კვლავ `www` მომხმარებელია. მხოლოდ socket-ის ჯგუფის შეცვლა საკმარისი არ იქნებოდა, რადგან `www`-ს პროცესი socket-ს გახსნიდა. ამიტომ FPM socket `www`-სთვის დახურულია, ხოლო პროქსი nginx-ის გარდა ყველას კეტავს. სხვა საიტების მომხმარებლები არ შეცვლილა.

კოდი `carapi`-სთვის ჩაწერადი არ არის. ჩაწერა შეუძლია მხოლოდ `data/` და `cache/` დირექტორიებში, სადაც ძველი საჯარო ნაკადი SQLite-სა და ფოტოს კეშს წერს.

## მართვა

```sh
/www/server/nginx/sbin/nginx -t
systemctl restart carapi-php-fpm.service carapi-fpm-proxy.service
/www/server/nginx/sbin/nginx -s reload
php bin/check-carapi-deploy.php
```

საერთო `php-fpm-84` ამით არ ჩერდება.

## ლოგი

ამ დომენის access log არის `carapi_safe`: მეთოდი, query-ს გარეშე გზა, სტატუსი, ზომა და `$request_time`. არ იწერება query string, Authorization, Cookie, body და Referer. პანელის `site_total` ჟურნალი, რომელიც სრულ URI-ს და cookie-ს წერდა, ამ ვირტუალურ ჰოსტს აღარ ეკუთვნის. იზოლირებულ nginx-ში ხელოვნური მარკერი access და error ლოგში არ გამოჩნდა.

## პროქსის შემოწმება

პროქსი root-ით მუშაობს. კლიენტის PID მხოლოდ kernel-ის `SO_PEERCRED`-იდან მოდის. დაშვებაა, თუ `/proc/<pid>/exe` ზუსტად `/www/server/nginx/sbin/nginx`-ია და მშობელი pid-ფაილში ჩაწერილი master-ია, რომლის exe იგივე ბინარია. პროცესის სახელი ან კლიენტის გამოგზავნილი PID არ გამოიყენება. `/proc`-ის შეცდომა, გაქრობილი PID და უცხო master კავშირს ხურავს. საერთო FPM-ზე fallback არ არის.

დადასტურდა: `www`-ს პირდაპირი კავშირი და `nginx` სახელის მქონე უცხო პროცესი იხურება; ცოცხალი worker ეშვება; master-ის pid-ფაილის შეცვლა worker-ს კეტავს. pid-ფაილი, პროქსის კოდი და დირექტორია `www`-სთვის ჩაწერადი არ არის.

`kernel.yama.ptrace_scope` არის 1. ამ პარამეტრზეა დამოკიდებული, რომ `www` პროცესი nginx worker-ს debugger-ით ვერ მიერთდება. ეს ცოცხალ nginx-ზე არ შემოწმებულა ინექციით. თუ Yama 0-ზე ჩამოვა, იგივე მომხმარებელს worker-ში კოდის შეყვანა შეეძლება და პროქსის შემოწმება აზრს კარგავს. სრული იზოლაცია ეს socket-ის უარი არ არის. სტანდარტული ალტერნატივაა სხვა საიტების PHP-ის ცალკე მომხმარებლები ან კონტეინერი. სხვა საიტები ამისთვის არ გადაკეთებულა.

## მომსახურება

`carapi-php-fpm.service` და `carapi-fpm-proxy.service` systemd-ითაა ჩართული boot-ზე. პროქსს `Restart=always` აქვს. შემოწმდა: პროქსის მოკვლა და ავტომატური დაბრუნება, FPM restart, nginx graceful reload. საიტი 200-ზე დარჩა. საერთო `php-fpm-84` აქტიური დარჩა. უქმე კავშირს 30 წამის timeout აქვს, ერთდროულად არაუმეტეს 64 გადაცემისა.

შემოწმება, კონფიგურაციის გადაწერის გარეშე:

```sh
php bin/check-carapi-deploy.php
```

## Rollback

მთლიანი `nginx.conf` არ დააბრუნოთ: იქ სხვა საიტების გვიანი ცვლილებებიცაა. CarAPI-ს ცვლილებაა `include carapi-log.conf;` ხაზი და დომენის ვირტუალური ჰოსტი.

```sh
cp /www/server/carapi/backups/car.socialsave.cc.conf.YYYYMMDDHHMMSS /www/server/panel/vhost/nginx/car.socialsave.cc.conf
# nginx.conf-იდან ამოშალეთ მხოლოდ ხაზი: include carapi-log.conf;
/www/server/nginx/sbin/nginx -t && /www/server/nginx/sbin/nginx -s reload
systemctl disable --now carapi-fpm-proxy.service carapi-php-fpm.service
```

`nginx -t` წარმატების გარეშე reload არ გააკეთოთ.

## გამორთვა

პრობლემისას სამივე `env[CARAPI_*_ENABLED]` დააბრუნეთ `0`-ზე `/www/server/php/carapi/php-fpm.conf`-ში და გადატვირთეთ მხოლოდ `carapi-php-fpm.service`. პროქსი მასთან ერთად ბრუნდება. მონაცემები არ იშლება და მთლიანი `nginx.conf` არ ბრუნდება.

შემოწმება კვოტის გარეშე: `php bin/check-carapi-health.php`.

## საჯარო ჩართვამდე

სამი დროშა production-ში 1-ია. fake transport production-ში არ გამოიყენება.

SMTP, 2026-09-26: კონფიგურაცია მიერთებულია (`CARAPI_MAIL_TRANSPORT=smtp`, `mail.socialsave.cc:587`, `tls`, `info@socialsave.cc`, `CARAPI_APP_URL=https://car.socialsave.cc`). პაროლი მხოლოდ `/www/server/carapi/mail.env`-ში და FPM-ის გარემოშია. `clear_env=yes` შენარჩუნებულია. Postfix-ის 587 პორტი Let's Encrypt-ის `*.socialsave.cc` სერტიფიკატს იძლევა, ვადა 2026-12-22. AUTH TLS-ის შემდეგ გავიდა. სატესტო წერილი `sh.chkhvirkia@gmail.com`-ზე გაიგზავნა, Gmail-მა `250` დააბრუნა და მომხმარებელმა ამ ერთი წერილის მიღება დაადასტურა. ეს მომავალი მიწოდების გარანტია არ არის. `php bin/check-carapi-deploy.php` აბრუნებს `ok`.
