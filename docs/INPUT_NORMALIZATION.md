# Zorunlu otomatik büyük harf giriş sözleşmesi

Yetki: [Issue #36](https://github.com/7sebahattin/max/issues/36), sahibinin 2026-11-06 tarihli devam ve uygulama talimatı. Başlangıç: `main` / `1d473fe8bea6de3035fcdf02b5a27d0dcccf437c`. Yerel dal: `feat/max-uppercase-inputs`. GitHub CI/push/yeni PR hâlâ askıdadır; merge veya dağıtım yapılmaz.

Bu geliştirme, merkezi normalizer ve desteklenen giriş adaptörlerini gerçek kodla ekler. **Bütün Dolibarr girişlerinin kurulu ortamda doğrulandığı anlamına gelmez.** Aşağıdaki kapsam/engel tablosu ürünün genel kabul kapısıdır. PR #34'ün henüz birleşmemiş Orders 0.3 dosyaları kopyalanmaz; bu dal main'deki Orders 0.2 üzerine kuruludur ve #34 sonrasında açıkça uzlaştırılmalıdır.

## Kural ve korunan değerler

İş adı, açıklama, adres, not ve benzeri sınıflandırılmış iş metni otomatik büyük harf olur. Kullanıcı Caps Lock açmaz; gerçek input değeri değişir. Aynı kural sunucuda doğrudan servis/API/import çağrılarında uygulanır. CSS-only çözüm veya kullanıcıya aç/kapat seçeneği yoktur.

[MaxInput](../modules/maxcore/class/input.class.php) mevcut PHP intl/mbstring ile Unicode NFC → ICU `tr-Upper` → NFC kullanır. Sabit normalizasyon profili `tr_TR`, arayüz dilinden bağımsızdır. Örnek: `istanbul ıslak çiçek → İSTANBUL ISLAK ÇİÇEK`, `straße → STRASSE`. Uzunluk dönüşümden sonra doğrulanır; genişleme taşarsa hata veya belge önerisinde needs_review olur. Sessiz kesme/birleştirme yapılmaz. Trim/boşluk sadeleştirme kuralı eklenmez; eski alan doğrulayıcıları kendi sözleşmelerini korur.

Şifre/token/session/CSRF, imza/hash, e-posta, URL/path, teknik ve case-sensitive referans/SKU, JSON/enum anahtarları, sayılar/tarihler ve kaynak belge baytları dönüştürülmez. İş metni içindeki açık URI/e-posta/path parçaları da korunur. Zengin metinde yalnız iş metni düğümleri değişir; attribute/link değerleri, code/pre/script/style gibi teknik içerik korunur. Bu işlem sanitizer değildir: native HTML temizleme ve yetki/CSRF doğrulamaları devam eder. DTD/entity ve içerik kaybına yol açacak sarmalayıcı kaçışı reddedilir.

Ham PDF/XLSX/mail kanıtı ve hash'i aynıdır. İçe alınan iş önerisi ayrı normalize edilir; raw hücreler, teknik ürün kodu ve sayısal literal değişmez. Üst sınır aşımında raw değeri kesilmiş gibi yanlış işaretlenmez. Eski kayıtlar topluca güncellenmez; yeni gönderilen alanlar normalize edilir. Mevcut native unique/kimlik kontrolleri korunur, çakışma otomatik birleştirilmez.

## Yerleştirilen mekanizmalar

- [Alan kayıt defteri](../modules/maxcore/input-fields.json): açık route/alan/rol/uzunluk listesi. Şu anda 20 web route ailesi, 7 native API kaynağı ve 7 native import profili vardır. Import wizard profil adı business text olarak 50 karakterle sınıflandırılmıştır. Listede olmayan alanlar kendiliğinden iş metni sayılmaz; kabul kapsamında sınıflandırılmamış olarak BLOCKED kalır.
- MAX Core `updateSession` native hook'u, kayıt kodundan önce tanımlı GET/POST/REQUEST alanlarını dönüştürür. Dolibarr `GETPOST` varsayılan olarak query'yi gövdeden önce okuyabildiğinden ikisi de normalize edilir. Kimlik/CSRF/action alanları değiştirilmez.
- Native `beforeApiCall`, Restler'ın **public** `getRequestStream()` arayüzünü `handle()` öncesinde kullanır; Reflection/private property/core patch yoktur. Hook anında requestMethod henüz null olabileceğinden native public method resolver kullanılır. Tanımlı yazma kaynakları JSON gerektirir, sorgu fallback'i de normalize edilir, gövde/çıktı 256 KiB ile sınırlıdır. JSON taşımasında yalnız sınıflandırılmış string token'ları değiştirilir; finansal sayı literalleri (ondalık hassasiyet ve büyük tamsayılar dahil), opaque string kaçışları, anahtarlar ve kalan baytlar aynen korunur. Yinelenen JSON anahtarları belirsiz yorumlamayı önlemek için reddedilir. `lines` alt alanları yalnız orders/invoices/shipments kaynaklarında sınıflandırılmıştır. Başarısız dönüşümde istek generic 400/413/415/503 ile sonlanır; native auth ve object izinleri yerine geçmez.
- Native `ImportInsert`, bilinen kolonları raw satır kopyasında dönüştürür ve native `import_insert` yöntemini tam bir kez çağırır. Native satır işlemi dış transaction ile çevrelenir; hata/uyarıda rollback ve manuel inceleme, başarıda native sayaç güncellemesi vardır. Native finans/stok SQL'i yazılmaz; importer'ın kendi yetki, tip, unique ve transaction davranışı tam kurulumda ayrıca doğrulanmalıdır.
- MAX servis sınırları: marka adı; Orders variant/packaging/currency ve A/B belge önerisi metinleri; Customs office/origin/destination/goods_summary; Mail hesap etiketi. Doğrudan servis çağrısı da normalize edilir, tarayıcıya güvenilmez. Role guard'ları önce çalışır. Teknik kaynak/ref/enum alanları korunur.
- [Tarayıcı adaptörü](../modules/maxcore/js/input.js): gerçek değer, paste/input/change/blur/submit, IME bitişi, Unicode genişlemesine göre caret ve dönüşüm sonrası uzunluk. Dinamik alanlarda delegasyon; password/hidden/file/readonly dönüştürülmez. CKEditor public getData/blur adaptörü vardır; gerçek native editor kabulü henüz NOT RUN'dır. Client zengin metin fragment'i inert template içinde işlenir; dış kaynak istenmez.

MAX Core kaynak paketi 0.3.0'a yükselir; input kullanan MAX Orders/Customs/Mail descriptor'ları `modMaxcore` bağımlılığını bildirir. Eski Core paketini tek başına bırakıp yeni bağımlı modülü kurmayın. Modül kimlikleri, menü/izin ID'leri ve native on/off mekanizması değiştirilmez. Core kapalı/kurulu değilse native hook kapsamı BLOCKED'dır; bu durum genel büyük-harf uyumu gibi gösterilemez. Core'u kullanıcı özelliği gibi kapatmak onaylı bir normalizasyon istisnası değildir.

## Kapsam ve kabul kapıları

| Yüzey | Kod / yerel kanıt | Kurulu native kabul |
|---|---|---|
| Cari/supplier/contact ad-adres; ürün label/description; depo metinleri | Native web route ve merkezi API/import adaptörleri; unit ve query-bypass testi | NOT RUN |
| Sipariş/fatura/sevkiyat açıklama/not/ürün satırı metinleri | Web/API satır alanları, bilinen import profilleri | NOT RUN |
| Yasal şirketin kendi ad/adres düzenleme ekranı | Açık business alanları; vergi, mail/web ve teknik kodlar korunur | NOT RUN; marka formundan yasal kimliğe yazılmaz |
| Native listelerde tanımlı ad/açıklama/not filtreleri | GET/POST kayıt defteri; teknik ref/sqlfilters korunur | NOT RUN |
| MAX marka ayarı, gümrük iş alanları, Mail hesap etiketi | Doğrudan servis sınırı + tarayıcı | Unit PASS; kurulu HTTP/DB NOT RUN |
| Orders manuel facts ve 0.2 A/B kaynak önerileri | JSON anahtarları/SKU korunur; gerçek XLSX testi | Dosya testi PASS; kurulu HTTP/DB NOT RUN |
| MAX Mobile | İş verisi giriş/kayıt servisi yok; mevcut modül kaynakları değişmez | Ortak kurulum NOT RUN |
| Ham mail/PDF/XLSX, asset selector, ref, credentials, teknik enum | Açık integrity istisnası, byte/typed değer korunur | Yerel koruma testleri PASS |
| PR #34 Orders 0.3 çok satırlı kaynaklar | Henüz main'de değil; kod kopyalanmadı | BLOCKED: #34 uzlaştırma ve ek adaptasyon gerekir |
| Yeni/üçüncü parti API, extrafields, bilinmeyen import profili | Registry'de yok; otomatik genel uyum iddiası yok | BLOCKED: ayrı sınıflandırma/adaptör gerekir |
| Native ORM/CLI/cron doğrudan create/update | Yeni işler `MaxInput` kontratını kullanmalı; hook'u atlayan mevcut işler kapsanmıyor | BLOCKED: envanter ve native pre-write desteği gerekir |
| Karışık hassas contact/analysis/source blob'ları | Schema/ham kanıt güvenliği için opaque kabul edilir | İş metni alt alanları için BLOCKED: typed editor/sözleşme gerekir |
| Rich editor gerçek yazım/caret/accessibility, full mobile native | Public adaptör hazır, sentetik form testi ayrı | NOT RUN / manuel kapı |

Bu tablo desteklenmeyen işlemin çalıştığı izlenimini vermez. Bilinmeyen business alanını registry dışında bırakarak kuraldan muaf tutmak yasaktır: yeni fonksiyon kabulü sınıflandırma ve regresyon olmadan kapanamaz. Nümerik-only/opaque teknik native yolların dönüşmeden geçmesi global business kapsamı kanıtı sayılmaz.

## Yeni fonksiyon geliştirme kapısı

1. Her giriş için business text / rich text / technical / immutable source sınıfını belirtin; gerçek native field/path ve uzunluk kanıtını kontrol edin.
2. Kayıt defterine ve authoritative servis/API/import adaptörüne ekleyin; browser tek başına yeterli değildir.
3. Türkçe i/ı/İ/I, NFC, genişleme/uzunluk, paste/IME, doğrudan API/query bypass, teknik istisna ve raw-hash korumasını test edin.
4. Native rol/CSRF/entity ve unique davranışını koruyun. Yetkisiz işlem öncesinde DB veya kaynak normalizasyonu yapılmamalıdır. Sadece hook return -1'in isteği durdurduğu varsayılmamalıdır.
5. Yeni native/third-party yüzeyi doğrulanmadıysa BLOCKED; unit'i native kurulum testi diye göstermeyin. `AGENTS.md` bu kapıyı zorunlu kılar. Yerel policy testi kayıt defteri rol/sınırlarını, Core bağımlılıklarını ve CI dosyalarının değişmediğini denetler.

## Yerel doğrulama ve üretilebilirlik

Cari/kişi devamı: [INPUT_PEOPLE_IMPORT.md](INPUT_PEOPLE_IMPORT.md). Kişi ad/soyad web/API/import sınırı native SQL'deki 50 karaktere eşitlenir; kişi private/public notları merkezi rich-text sınıfına eklenir. `societe_1` ve `societe_2` CSV/XLSX HTTP kabulü ile korunan kişi snapshot'ı eklenir. Genel ilerleme ve açık kabul payı [PROJECT_STATUS.md](PROJECT_STATUS.md) içinde ayrı hesaplanır.

HTTP wizard devamı: [INPUT_IMPORT_WIZARD.md](INPUT_IMPORT_WIZARD.md), `produit_1` CSV/XLSX için gerçek yükleme/eşleme/simülasyon/commit, rol/CSRF, batch rollback ve kaynak hash kabulünü tanımlar. Step-5 token koruması native hook sınırlarında eklenir; native Run Import linki token'lı POST'a çevrilir. Profil adı merkezi MaxInput ile dönüştürülür. Diğer profiller ve genel ürün kabulü ayrı kapılardır; exact SHA kanıtı yerel rapordadır.

### Windows native devam çalışması (2026-11-06)

Bundle Windows'a geri yüklendi. [Native Windows kayıt ve yeniden üretim](INPUT_NATIVE_WINDOWS.md), gerçek kurulumun kapsamını, yeni adaptör düzeltmelerini, sabit ticari snapshot sınırını ve kalan NOT RUN/BLOCKED kapılarını tanımlar. Yeni [yerel native runner](../scripts/input_native_local.py) resmi yükleyici, taşınabilir MariaDB, gerçek HTTP oturumu/API ve native CSV/XLSX reader/importer kullanır; eski sentetik test gruplarını veya Docker kimlikli ortak 63'ü temsil etmez. Exact final commit kanıtı ignored `.local/input-native-summary.json` içinde tutulur.

Native import hücreleri `{val,type}` taşır: CSV sıfır, XLSX bir tabanlı; mapping bir tabanlıdır. Merkezi adaptör artık doğru business hücrenin yalnız `val` değerini dönüştürür; typed null/blank, metadata, teknik kolon ve ham kaynak korunur. Native CSRF tarafından kaldırılmış POST yeniden oluşturulmaz. Gerçek ürün/cari card taşmaları Core'a ait sınırlı form reflow ile düzeltilir; sıfır overflow koşulu gevşetilmez. Yeni unit regresyonları bu native farkları kapsar.

Aşağıdaki 245 kontrol tablosu **aktarılmış ilk Linux kaydının tarihçesidir**; yeni Windows/native çalışmanın sayıları veya exact SHA kanıtı değildir. İlk kayıttaki geniş NOT RUN kapıları yalnız yeni native envanterde açıkça belirtilen altküme için daralır. Bütün 19/7/7 yüzey, HTTP import wizard, gerçek CKEditor ve ortak 63 hâlâ tamamlanmış sayılmaz.

Mevcut uyumlu PHP CLI ve checksum-doğrulanmış Dolibarr 24.0.1 htdocs ile:

```sh
python3 scripts/input_test_local.py --php /path/to/php --dolibarr /path/to/htdocs --browser
```

Araç paket, browser veya servis kurmaz; Docker/Actions/provider çağırmaz. Browser için kurulu Playwright ve sandbox etkin `/usr/bin/chromium` kullanılır. Logs/raporlar ignored `.local/input-*` altındadır; yalnız sentetik veriler kullanılır.

Yerel test grupları: saf normalizer/servis guard/native adapter unit; değişmemiş pinned Restler + GETPOST kütüphaneleri; gerçek XLSX kaynak koruması; mevcut 32 extraction/güvenlik kontrolü; sandbox etkin Chromium ile beş sentetik form × 360/390/768 = 15 strict case, IME/paste/caret/submit/uzunluk ve dış istek olmaması; registry/policy denetimi. Sayılar ve exact yerel commit SHA sonuç kaydında tutulur. Bu gruplar tam Dolibarr DB/HTTP oturumu değildir.

Geliştirme sırasında native GETPOST query önceliği ve API hook'undaki null yöntem alanı bulundu ve gerçek kütüphane testleriyle düzeltildi. İlk browser harness'i document yerine aynı window'u yeniden kullanarak eski global state taşıdı; gerçek navigasyonla izole edildi. Eski test koşulları veya güvenlik kontrolleri gevşetilmedi.

### 2026-11-06 yerel sonuç kaydı

| Kontrol | Sonuç | Kapsam |
|---|---|---|
| Normalizer ve servis/adaptör unit | PASS 38/38 | Unicode, bypass, teknik değerler, JSON bayt bütünlüğü, import rollback/guard |
| Pinned native kütüphane | PASS 9/9 | Değişmemiş Dolibarr 24.0.1 Restler/GETPOST; kurulu oturum değildir |
| Gerçek XLSX kaynak koruması | PASS 5/5 | Ham kaynak/hash ve sayısal literal değişmezliği |
| Mevcut Orders extraction | PASS 32/32 | Önceki güvenlik ve kaynak koşulları korunur |
| Sentetik Chromium formları | PASS 98/98 | İçinde 15 strict viewport; gerçek native ekran değildir |
| Giriş policy ve yerel runner testleri | PASS 8/8 | Registry, kural, bağımlılıklar; timeout/eksik araç/hatalı çıkış eski PASS kaydını koruyamaz |
| Mevcut CI seçim / doctor / audit unit | PASS 15/15 + 14/14 + 26/26 | Yalnız yerel hafif testler |
| Repository policy, PHP lint, JS syntax, kaynak taraması | PASS | Kaynak taramasında bulgu yok |
| Kurulu native DB/HTTP/rol/CSRF ve ortak 63 | NOT RUN | Yayın öncesi kapı açık |
| GitHub Actions / push / yeni PR | NOT RUN | Sahibinin kota askısı korunur |
| GitHub Issue #36 sonuç yorumu | BLOCKED | Bu ortamda GitHub API okuması `Forbidden`; yazma denenmedi |

Yerel runner `.local/input-local-summary.json` içinde çalıştırılan HEAD'i kaydeder; commit sonrası aynı komutla kaynak SHA'sı doğrulanır. Yerel sonuçlar uzakta yayınlanmış CI kanıtı değildir. Sonraki iş: kurulu sentetik Dolibarr'da form/API/import, rol/CSRF/entity/unique ve değişmez finansal kayıt regresyonları; henüz sınıflandırılmamış girişlerin envanteri ve PR #34 birleşmesinden sonra Orders 0.3 uzlaştırması. Zorunlu büyük-harf sözleşmesi bu çalışmalarda da geçerlidir.

**GitHub CI/push/yeni PR: NOT RUN / sahibinin kota askısı.** Mevcut shared 63 native case bu SHA'da çalıştırılmadı; önceki PR sonucunu burada PASS diye kullanmayın. Tam kurulumda rol/CSRF/entity, unique collision, marka-yasal kimlik ayrımı, yeni Core bağımlılıklarının lifecycle ve UI beklentileri, aynı DB'de bank/stok/fatura değişmezliği ve tüm native/mobile yüzeyler yayın öncesi zorunludur. Eski fixture'ların business text case beklentileri yeni sözleşmeye uyarlanmalı; strict overflow/permission/immutability koşulları korunmalıdır.

Rollback: henüz yayınlanmamış yerel branch/commit'i bırakın; kurulum/migration/data rewrite yapılmadı. Gelecekte yayınlanmış olursa onaylı önceki kaynak paketlerini birlikte geri alın; teknik/native kayıtları veya ham belge geçmişini topluca dönüştürmeyin. Native Core kapalı kalırsa bu bir uyumlu ürün kabulü değildir. Merge, deployment, gerçek SMTP/HKS veya ücretli işlem bu iş kapsamında yoktur.
