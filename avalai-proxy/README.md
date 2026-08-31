# پروکسی مستقل AvalAI

این پوشه را روی سرور ایران قرار دهید. پروکسی هیچ وابستگی‌ای به Laravel یا Composer ندارد و فقط به PHP 8.1+ و افزونه `curl` نیاز دارد.

پاسخ AvalAI به‌صورت خام عبور داده می‌شود؛ status code، محتوای JSON یا SSE و هدرهای end-to-end حفظ می‌شوند. در صورت قطع ارتباط با AvalAI، پروکسی پاسخ استاندارد `502` برمی‌گرداند.

## تنظیمات سرور ایران

روش پیشنهادی، تعریف environment variable در PHP-FPM یا کنترل‌پنل هاست است:

```ini
PROXY_ACCESS_TOKEN=a-very-long-random-secret
AVALAI_UPSTREAM_BASE_URL=https://api.avalai.ir
PROXY_CONNECT_TIMEOUT=15
PROXY_REQUEST_TIMEOUT=180
PROXY_MAX_REQUEST_BYTES=20971520
```

اگر امکان تعریف environment variable ندارید، فایل `config.example.php` را با نام `config.php` کپی و مقادیر آن را تکمیل کنید. فایل `config.php` در git نادیده گرفته شده و نباید commit شود.

`PROXY_ACCESS_TOKEN` فقط رمز ارتباط سرور آلمان با پروکسی است. کلید واقعی AvalAI در این سرور ذخیره نمی‌شود و از هدر `Authorization` درخواست ورودی به AvalAI عبور داده می‌شود.

## Apache / هاست اشتراکی

کل پوشه را داخل یک subdomain با HTTPS، مثلاً `avalai-proxy.example.ir`، قرار دهید. فایل `.htaccess` درخواست‌های `/v1/*` را به `index.php` می‌فرستد. Document Root دامنه باید همین پوشه باشد.

نصب داخل زیرپوشه نیز پشتیبانی می‌شود. برای نصب فعلی، endpoint کامل این است:

```text
https://webtogram.com/AvalAIProxy/v1/chat/completions
```

## Nginx

نمونه تنظیم server block:

```nginx
server {
    listen 443 ssl http2;
    server_name avalai-proxy.example.ir;
    root /var/www/avalai-proxy;
    index index.php;

    client_max_body_size 20m;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param PROXY_ACCESS_TOKEN "a-very-long-random-secret";
        fastcgi_param AVALAI_UPSTREAM_BASE_URL "https://api.avalai.ir";
        fastcgi_read_timeout 200s;
        fastcgi_buffering off;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /(?:config|README|\.git) {
        deny all;
    }
}
```

برای production بهتر است secretها را در فایل جداگانه‌ی خارج از Document Root با `include` وارد Nginx کنید، نه داخل فایل version-controlled.

## تنظیم سرور آلمان (همین پروژه Laravel)

در پنل سوپرادمین، بخش «تنظیمات هوش مصنوعی»، یک رکورد AvalAI فعال بسازید و این مقادیر را وارد کنید:

- کلید API: کلید واقعی AvalAI
- مدل: برای مثال `gpt-4o-mini`
- آدرس API / پروکسی: `https://webtogram.com/AvalAIProxy` (Laravel مسیر `/v1/chat/completions` را اضافه می‌کند؛ آدرس کامل نیز قابل قبول است)
- توکن پروکسی: همان `PROXY_ACCESS_TOKEN` تنظیم‌شده روی سرور ایران

کلید API و توکن پروکسی در جدول `ai_provider_settings` به‌صورت رمزگذاری‌شده ذخیره می‌شوند. برای AvalAI هیچ تنظیمی در `.env` لاراول لازم نیست.

## تست

سلامت پروکسی:

```bash
curl -i https://webtogram.com/AvalAIProxy/health
```

تست واقعی chat completions:

```bash
curl -i https://webtogram.com/AvalAIProxy/v1/chat/completions \
  -H "Authorization: Bearer real-avalai-api-key" \
  -H "X-Proxy-Token: a-very-long-random-secret" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"سلام"}]}'
```

پروکسی فقط مسیرهای `/v1/*` را قبول می‌کند، redirect سمت upstream را دنبال نمی‌کند، TLS را اعتبارسنجی می‌کند و درخواست‌های بدون هر دو هدر امنیتی را با `401` رد می‌کند. هدر `X-Proxy-Token` به AvalAI ارسال نمی‌شود.
