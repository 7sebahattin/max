# Windows yerel native giriş doğrulaması

Issue #36; 2026-11-06. Aktarım bundle'ı ve kanıt ZIP'inin SHA256 değerleri `SHA256SUMS.txt` ile eşleşti. `feat/max-uppercase-inputs` dalı `399ddb7a6d15125f0cdbdcfd541dbf38d27b1e8e` üzerinde temiz geri yüklendi. Paket eski Linux çalışma zamanını içermiyordu. Yerel `origin/main` referansı yalnız bundle'daki bilinen başlangıç commit'ine oluşturuldu; remote eklenmedi ve güncel GitHub main/PR durumu varsayılmadı. Issue #1 web okuması 404 döndü; mevcut ürün sözleşmesi ve sahibinin açık talimatı kullanıldı.

## Çalışma zamanı ve sınırlar

- Değişmemiş Dolibarr **24.0.1**; arşiv SHA256 `cd5cb03c3f927007c7e4c63a02ebdebdcf81efd378603729bbcf6991ffa1b9c4`. Runner yalnız arşivi değil, htdocs içindeki her dosyayı arşivle karşılaştırır; sonunda aynı dosya kümesi/hash'leri tekrar doğrular. Yalnız geçici native `conf.php` ve external MAX modül kopyaları eklenir, sonunda kaldırılır.
- Mevcut Windows PHP **8.1.34**, ICU **70.1**; gerekli uzantılar ignored `.local/php.ini` içinde açıldı. Sistem PHP yapılandırması değişmedi. Bu çalışma, Compose'un pinned PHP 8.3 görüntüsünün kabulü veya dağıtım önerisi değildir.
- Taşınabilir **MariaDB 11.4.13**; Windows servisi kurulmaz. [Resmi arşiv](https://archive.mariadb.org/mariadb-11.4.13/winx64-packages/mariadb-11.4.13-winx64.zip), [resmi SHA256 listesi](https://archive.mariadb.org/mariadb-11.4.13/winx64-packages/sha256sums.txt): ZIP SHA256 `d62986d433eeebfde218560b276103831604a61e929e87f1a17f5aebd80257e2` doğrulandı.
- Python **3.12.14**, yerel bağımlılık dizinine Playwright **1.56.0**, Chromium **141.0.7390.37 / build 1194**. Chromium sandbox etkin; no-sandbox kullanılmaz.
- Her invocation ayrı `.local/input-native-<random>` dizini, rastgele DB/HTTP portu, veritabanı adı ve geçici parolalar oluşturur. DB ve HTTP yalnız `127.0.0.1` dinler. Resmi `install/step2.php` ve `step5.php` kullanılır. Normal DB kullanıcısının yetkisi yalnız yeni DB üzerindedir.
- Tarayıcı dış adresleri reddeder, native telemetry opt-out cookie'si kullanılır; JS hatası veya dış istek testi başarısız kılar. `MAIN_DISABLE_ALL_MAILS=1`; gerçek müşteri/hesap/SMTP/HKS/provider yoktur. **Windows süreçleri için Docker'daki gibi ağ egress izolasyonu kanıtlanmış değildir.** Ortak suite'in ağ/kimlik sözleşmesiyle eşdeğerlik iddiası yoktur.

## Düzeltmeler ve regresyonlar

1. Native import hücreleri düz string değildir: `{val,type}` taşır. CSV satırları sıfır, XLSX satırları bir tabanlıdır; mapping bir tabanlıdır. Merkezi `MaxInput::importRow` her iki biçimde doğru hücrenin yalnız business `val` değerini dönüştürür. Hücre anahtarları, metadata, null/blank, opaque değer ve kaynak baytları korunur. Seyrek/belirsiz satır, tutarsız null ve genişleme taşması reddedilir. Mevcut scalar unit sözleşmesi korunur.
2. Native CSRF işlemi `$_POST`'u kaldırabilir. Adaptör bu durumda POST'u yeniden oluşturmaz veya TypeError üretmez; native ret davranışı devam eder.
3. Gerçek ürün/cari formlarında 360/390 px, ayrıca cari formunda 768 px taşma bulundu. Yalnız bu iki native card için Core'un `input.css` ve form sınıflarıyla alanlar yeniden akar. Değer, teknik alan, kayıt adı ve odak sırası değişmez. İçerik kırpma, overflow gizleme veya tolerans eklenmedi; Maxmobile kaynakları değişmedi.
4. Native import hatalarının tipi/dosya/satırı kaynak değer/SQL/credential içermeden loglanır; kullanıcı generic hata görür.
5. Native WAF/access-denied yolları `exit(0)` dönebilir. Runner yalnız exit code ile PASS üretmez; her aşamanın son assertion kaydını, tüm kayıtların PASS olmasını ve final snapshot eşitliğini ister. WAF'ın CLI Windows yolunu URL sanarak durdurduğu ilk deneme **geçerli import PASS değildir**. CLI helper native WAF'a sabit native URL yolu verir; WAF fonksiyonu değiştirilmez veya devre dışı bırakılmaz.

Mevcut normalizer, extraction, policy ve sentetik browser testleri yeniden yazılmadı; PHP ini/browser yolu seçimi ve yeni regresyonlar eklendi. Eski Docker/shared helper'ları, workflow'lar ve güvenlik/overflow koşulları değiştirilmedi.

## Native kapsam envanteri

| Yüzey | Bu runner'ın kontrolü | Kalan kapı |
|---|---|---|
| Products API | Gerçek POST/PUT/GET, Türkçe/NFC/ß, rich text/link, SKU, duplicate key, uzunluk, auth/rol/entity | Diğer resource/line endpoint'leri NOT RUN |
| Product web | Oturumlu doğrudan POST, query önceliği, geçerli token ile GET yazımı, geçersiz CSRF, yetkisiz kullanıcı, taşma reddi | Bütün update/clone/note varyantları NOT RUN |
| Product/cari/Core form değerleri | Gerçek native ekranlarda actual input ve 360/390/768 px, sıfır taşma | Cari HTTP kayıt/clone, legal şirket ekranı ve gerçek CKEditor/caret NOT RUN |
| `produit_1` import | Gerçek CSV/XLSX reader → native HookManager → native importer → DB; ham hash, SKU, duplicate rollback, uzunluk ve sayaç | HTTP import wizard/CSRF/rol akışı ve diğer 6 profil NOT RUN |
| Core marka | Doğrudan native DB servisi, Türkçe değer, entity 1/2 ayırımı ve legal snapshot | Marka HTTP save/upload/CSRF kabulü NOT RUN |
| Modül lifecycle | Beş MAX modülü native aktivasyon, yazan modüllerde Core dependency, native Core off/on ve kapalıyken servis reddi | Tüm dependent cascade/kurulum UI kombinasyonları NOT RUN; Core kapalı native girişler BLOCKED |
| Ticari değişmezlik | Aşağıdaki sabit before/after sözleşmesi | Dolu native invoice-payment/lot/shipment kapsamı NOT RUN |
| Bütün 19 web / 7 API / 7 profil | Registry ve unit korunur; yalnız yukarıdaki altküme kurulu testlidir | Geri kalan native varyantlar NOT RUN |
| Extrafields/üçüncü parti/bilinmeyen route-import | Sınıflandırılmış authoritative adaptör yok | BLOCKED |
| Doğrudan ORM/CLI/cron | Fixture oluşturmak için native API kullanımı normalizasyon kanıtı sayılmaz | Mevcut bypass yüzeyleri BLOCKED |
| PR #34 Orders 0.3 | Aktarım dalındaki Orders 0.2 korunur | Uzak durum doğrulanmadı; uzlaştırma BLOCKED |
| Ortak 63 native kabul | Değişmemiş suite Docker/Compose kimliği, internal ağ ve fake IMAP ister | NOT RUN; Docker ve WSL kurulu değil |

**Unique kapsamı:** Native ürün SKU çakışması API ve import üzerinde ret/rollback ile sınanır. SKU dönüştürülmez. Ürün label'ı native unique alan değildir; iş adına native unique constraint varmış gibi bir kanıt üretilmez.

## Snapshot sınırı

Bütün sentetik ticari fixture'lar tamamlandıktan sonra `before.json` **bir kez** yazılır. Ürün stok satırı/hareketi, draft sipariş/satırı, draft fatura/satırı, hayalî banka hesabı/IBAN ve iki banka satırı doludur. `paiement`, `paiement_facture`, lot ve shipment tabloları bu dar fixture'da boştur; bunların değişmediği kontrol edilir fakat dolu ödeme/lot/sevkiyat kabulü iddia edilmez. Gerçek fatura kesilmez veya ödenmez.

Snapshot tüm stock/order/shipment/invoice/bank/payment tablolarını, sabit ticari müşteri/ürün satırlarını, diğer entity'deki ürünü ve native legal şirket sabitlerini kapsar. Yeni test product kayıtları bu sabit ürün/müşteri kimlik kümesine dahil değildir; izin verilen yeni test kayıtları ticari ledger değişikliğini gizleyemez. Normalizasyon, marka değişiklikleri ve Core toggle bittikten sonra aynı sorgu/küme ile `after.json` üretilir; hash ve sayılar birebir eşit olmak zorundadır. Baseline yenilenmez.

## Yeniden üretim

Geliştirme koşusunda gerçek native kurulumda **37 fixture + 50 HTTP/browser + 24 import/lifecycle/snapshot assertion PASS** elde edildi; dokuz strict native viewport bu 50 kontrolün içindedir. Sonrasında wizard'ın dış transaction/simulation ve batch rollback regresyonları eklendi. Teslim SHA'sındaki son sayı/sonuç `.local/input-native-summary.json` ve ilgili run dosyalarından alınır; development dirty-tree sonucu exact commit kanıtı yerine kullanılmaz.

| Kontrol | Windows sonucu / sınır |
|---|---|
| Normalizer/adaptör unit | PASS 44; CSV/XLSX hizalama ve kaldırılmış POST dahil |
| Native kütüphane / gerçek kaynak / mevcut extraction | PASS 9 + 5 + 32; native DB kabulünden ayrı |
| Sentetik Chromium | PASS 98; 15 viewport; gerçek native ekran sayılmaz |
| Policy/runner/temizlik unit | PASS 14; yabancı config/ağaç, açık port, readonly lock ve eksik kabul kaydı |
| Gerçek native forms/API/CSV/XLSX/Core/snapshot | PASS geliştirme altkümesi; exact final SHA raporu ayrı |
| Eski CI seçim testi | FAIL/ERROR: 14 PASS + 1 NTFS filename hatası; test aynen korunur |
| Ortak 63 / Docker Compose validation / smoke | NOT RUN: bu bilgisayarda Docker/WSL yok |
| Bilinmeyen girişler / ORM-CLI-cron bypass / Core kapalı native | BLOCKED; genel ürün uyumu ilan edilmez |
| GitHub push/Actions/PR/merge/deployment | NOT RUN; sahip askısı korunur |

Gerekli araçları önceden yerel ücretsiz kaynaklardan hazırlayın; runner download/kurulum/Actions başlatmaz. Sistem PHP ini'sini değiştirmeden gerekli uzantıları içeren `.local/php.ini` sağlayın. Aşağıdaki yollar bu Windows bilgisayarında kullanılan yollardır:

```powershell
$env:PYTHONUTF8 = '1'
$env:PYTHONPATH = "$PWD/.local/python-packages"
$env:PLAYWRIGHT_BROWSERS_PATH = "$PWD/.local/browsers"
$env:MAX_INPUT_BROWSER_EXECUTABLE = "$PWD/.local/browsers/chromium-1194/chrome-win/chrome.exe"
$pythonExe = "$env:USERPROFILE/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe"
& $pythonExe scripts/input_test_local.py --php (Get-Command php).Source --php-ini .local/php.ini --dolibarr .local/upstream/htdocs --browser
& $pythonExe scripts/input_native_local.py --php (Get-Command php).Source --php-ini .local/php.ini --dolibarr .local/upstream/htdocs --mariadb .local/mariadb/mariadb-11.4.13-winx64/bin
& $pythonExe -m unittest discover -s tests/input -v
& $pythonExe scripts/check.py
& $pythonExe scripts/max_source_audit.py --json
```

Yeni/başka çalışma üzerinde değil, bu işin final commit'i ve temiz worktree üzerinde yeniden çalıştırın. Exact SHA, worktree durumu ve aşamalar `.local/input-native-summary.json` / `.local/input-local-summary.json` içindedir. Run dizinindeki `fixture-checks.json`, `http-checks.json`, `viewports.json`, `verify-checks.json`, `before.json`, `after.json` paylaşılabilir sentetik sayısal kanıtlardır. Log, config, credentials, session ve ham kaynak dosyaları paylaşım paketine alınmaz. Eski aktarım kanıtları yeni Windows sonucu değildir.

## Temizlik ve başarısız denemeler

Runner yalnız kendi PID'lerini durdurur ve kendi run dizininin çözümlenmiş alt yollarını siler. DB/documents/session/credentials ve geçici upstream conf/modül kopyaları kaldırılır; portların kapandığı ve bütün upstream dosyalarının ilk hash'lerine döndüğü doğrulanır. İndirilen ücretsiz araç/arşivler sonraki yerel test için ignored `.local` altında kalır.

İlk Windows denemelerinde native 12 karakter banka ref sınırını aşan fixture ve installer'ın salt okunur lock/conf dosyaları nedeniyle teardown hatası bulundu. Fixture düzeltildi; kontrollü readonly temizliği eklendi. Kesilen iki deneme `--recover-run` ile marker, root, DB port ve varsa config/module hash sahipliği doğrulanarak temizlendi. Native süreçler yeniden kullanılmadı. Recovery yabancı config, açık DB portu, parent/sibling ağaç ve değişmiş modül üzerinde durur; unit testleri bunu doğrular.

Mevcut `tests/coenabled/test_ci_policy.py` Windows koşusunda 14 PASS + 1 ERROR verdi: satır sonu içeren gerçek Git filename fixture'ı NTFS'de oluşturulamıyor (`WinError 123`). Test değiştirilmedi, skip veya tolerans eklenmedi; suite PASS ilan edilmez. Diğer yerel kanıt/cleanup testleri ayrı raporlanır. Compose validation/smoke ve ortak 63 NOT RUN; GitHub Actions/push/PR/billing/merge/deployment askısı aynen geçerlidir.

Sonraki somut iş: sınıflandırma ve merkezi MaxInput sözleşmesini koruyarak gerçek HTTP import wizard'ının CSV/XLSX başarı/rol/CSRF/rollback akışlarını eklemek; ardından ücretsiz Docker-capable yerel ortamda değişmemiş ortak 63'ü ve kalan native girişleri doğrulamak.
