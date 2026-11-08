# Cari/kişi web ve API yazma kabulü — Issue #36

Başlangıç yerel commit'i `b5026d9059d3abe4ab3cce65a36754213596bdd2`. Mail/çeviri ve eski ASYA Raporlar sayfası son fazda kalır. Yalnız disposable, sentetik yerel Dolibarr 24.0.1 kullanılır. Bu 6 Kasım yerel kayıt yeni Actions sonucu değildir; 8 Kasım owner update scoped draft PR ve standart hosted runner'ları yetkilendirdi. Billing, merge ve deployment kısıtları sürer.

## Sınıflandırma ve merkezi düzeltmeler

- Kişi `note_public` / `note_private` alanları native `contact/card.php` add/update ve Contacts API üzerinden yazılabiliyordu; bu iki web/API registry listesinde yoktu. Ortak MaxInput'a `html,65535` olarak eklendi. Import `societe_2` not kuralları zaten mevcuttu. E-posta, telefon, bağlantı, kimlik ve token sınıfları değişmez.
- Cari `town` native `llx_societe.sql` içinde **varchar(50)**; mevcut 64 kuralı web `societe/card.php`, Thirdparties API ve `societe_1` import için 50'ye düzeltildi. Şehir 50 sınırı native library testinde gerçek pinned SQL ile eşlenir. Kişi `town` SQL'de 255 olsa da mevcut daha dar 64 kuralı genişletilmez.
- Cari ad/alias/adres/şehir ve notlar; kişi ad/soyad/görev/adres/şehir ve notlar business text/rich text'tir. Müşteri kodu, e-posta/URI, ID/parent ilişkisi, ülke/durum ve CSRF/API token'ları teknik/opaque kalır. Yeni normalizer, auth sistemi veya ERP gölge kaydı yoktur; upstream/schema değiştirilmez.

## Gerçek native akış

[crm_http.py](../tests/input/crm_http.py) iki kaynağın native API create/update/read/delete ve native browser create/edit ekranlarını kullanır. SQL/session seed'i yapılmaz.

- API create sonucunun bağımsız GET ile okunması; Türkçe/NFC/genişleme, native kod sözleşmesi, e-posta/URI path-query bütünlüğü ve parent ilişkisi.
- Native müşteri kodu validator'ı (`mod_codeclient_monkey` / `elephant`, `verif(&$code)`) kodu ASCII uppercase kanonikleştirir. Bu native, case-insensitive kod sözleşmesi korunur; MaxInput bu teknik alana dokunmaz. CSV/XLSX generic importun native nesne validator'ını çağırmamasıyla farkı ayrıca korunur; case-sensitive SKU/ref değerlerine genel bir casing kuralı eklenmez.
- Native `Societe::update` ayrıca `clean_url()` çağırarak URL alanındaki DNS host adını küçük harfe kanonikleştirir; bu native davranış değiştirilmez. Persistence testinde `/Case?Key=aB` path/query case'i exact, host native canonical olarak sınanır. MaxInput URI'yi uppercase etmez; prose/HTML içindeki URI'nin tamamı exact korunur.
- Native `CommonPeople::setUpperOrLowerCase()` e-postayı lowercase yaptığı için pre-dispatch normalizer tek başına exact persistence sağlayamıyordu. Desteklenen native COMPANY/CONTACT CREATE/MODIFY trigger'ında, yalnız yetkilendirilmiş aynı kayıt/aktif entity ve request-scope için native `CommonObject::setValueFrom` ile case korunur. Custom SQL, ikinci depo, recursive trigger veya upstream değişikliği yoktur; aynı outer transaction içinde hata halinde rollback edilir. Native değerden yalnız harf case'i farklıysa düzeltilebilir; sanitizing/validation sonucu değiştirilmez. API partial PUT'ta email yoksa mevcut native değer okunup korunur; farklı query/body email değerleri 400 ile reddedilir. API GET veya ilişkisiz trigger'lar yazmaz. Core kapalı, ORM/cron, türetilmiş kayıtlar ve farklı resource/aksiyonlar bu dar adaptörün kapsamı değildir.
- API rich not update'inde prose dönüşümü ve HTML link case'inin korunması; her iki kişi notunun native web POST üzerinden dönüşümü.
- Her iki resource için auth/role create/update reddi; başka entity'deki customer GET/PUT reddi. Fixture'a entity=2 müşteri/kişi eklendi; sabit snapshot bunların bütün kolonlarını da korur. **Contacts entity izolasyonu BLOCKED:** native `checkUserAccessToObject` (security.lib.php, checkparentsoc dalı) MultiCompany olmadan bütün yetkilere sahip iç kullanıcı için entity filtresi uygulamıyor. Entity=2 kişiyi GET ile okuyabilme 200 olarak yeniden üretilir; yetkili kullanıcıyla bu kişiye PUT yapılmaz. Denied kullanıcı PUT'u yine 403 olmalıdır. `crm-limitations.json` bu bulguyu ayrıca BLOCKED kaydeder; diagnostic assertion'ın PASS olması izolasyonun PASS olması değildir. MAX tek müşteri için ayrı kurulum/DB kullanır; ücretli MultiCompany etkinleştirilmez veya paralel auth yazılmaz. İki ayrı kurulum izolasyonu NOT RUN kalır.
- Cari name/alias 128, address 255, town 50; kişi firstname/lastname 50, poste/address 255, mevcut town 64 sınırlarında tam dönüşüm sonrası kabul ve bir sonraki genişlemede 400/değişmeyen kayıt.
- Duplicate JSON key reddi. Native web edit alanında gerçek browser value dönüşümü; JS çalıştırmayan doğrudan update POST'u; query-before-body önceliğinin normalizasyonu; eksik/yanlış CSRF ve denied kullanıcıyla değişmeyen kayıtlar.
- Native company/contact create formu ile gerçek browser submit; API üzerinden persistence ve opaque code/email kontrolü. Web ad ve cari town overflow'unda önceki kaydın korunması.

Sadece bu koşunun oluşturduğu ID'ler temizlenir; önce contact, sonra company native DELETE API kullanılır. Korunan mevcut müşteri/kişi, entity=2 kayıtlar ve banka/IBAN/stok/sipariş/fatura/ödeme/yasal snapshot baseline'ı daraltılmaz, yenilenmez. Test/temizlik başarısızlığı PASS değildir.

## Kanıt ve sınırlar

Core 0.3.1 `module_parts.triggers` kaydını ekler; mevcut kurulumun ilerideki yetkili upgrade'inde native modül metadata refresh'i gerekir. Bu iş canlı kurulum upgrade'i yapmaz. Unit guard'lar eşleşmeyen ID/entity/event, farklı native içerik, ambiguous query/body, boş request context ve native ORM hata sinyalini sınar; bunlar gerçek DB rollback kanıtı gibi sayılmaz. Installed HTTP kabulü gerçek native create/update/trigger/ORM/persistence ve ticari snapshot ile ayrıca doğrulanır.

Runner yeni `native_crm_http` aşamasını `crm-checks.json` ve terminal assertion ile doğrular. Hedefli geliştirme için standart Windows native komutuna `--http-suite crm` eklenebilir; diğer HTTP aşamaları NOT RUN kalır. Final teslim aynı clean HEAD'de varsayılan `--http-suite all`, yerel kontroller ve owned cleanup gerektirir. Exact SHA ve sonuç `.local/input-native-summary.json`, güvenli assertion/hash paketi `.local/input-evidence-manifest.json` içindedir; raw/log/config/credential/session paylaşılmaz.

Önceki yedi import profilinin CSV/XLSX insert/strict_line kabulü yeni town sınırıyla yeniden çalışır. Eski 25 ürün hedefinin %28 kanıtlı / %72 açık-kısmi oranı değişmez. Bu test tüm native web/API coverage iddiası değildir: CKEditor etkileşimleri, bütün CRM özel aksiyonları, external-user/owner-scope çeşitleri, warehouse/order/invoice/shipment write yüzeyleri, update/bulk import, ORM/cron/third-party, Core kapalı yollar ve Docker ortak 63 ayrı açık/BLOCKED kapsamdır.

Rollback: bu commit'in merkezi registry alan/sınır düzeltmelerini geri almak önceki normalizasyon açığını geri getirir; test/runner/fixture/dokümanlar birlikte izlenmelidir. Üretim veri/schema migrasyonu yoktur, deployment yapılmaz.
