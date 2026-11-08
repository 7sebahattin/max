# Native sipariş web/API yazma kabulü — 7 Kasım 2026

Issue [#36](https://github.com/7sebahattin/max/issues/36), başlangıç `626b022f0053182d8409f9ad712acc9990f07fd9`. Issue #1 içeriği 8 Kasım'da public GitHub API üzerinden yeniden okundu; güncel `AGENTS.md` ve `PRODUCT_SCOPE.md` uygulanır. Owner 8 Kasım'da public depoda scoped draft PR ve standart hosted Actions'ı yetkilendirdi. Bu stage `codex/max-order-writes` dalında, [draft PR #38](https://github.com/7sebahattin/max/pull/38) içinde ve input-policy [PR #37](https://github.com/7sebahattin/max/pull/37) üstüne yığılmıştır; base input-policy PR exact head'inde 18/18 Actions kontrolü geçti. Bu PR'ın kendi exact-head kontrolleri ayrıca raporlanır. Merge/deployment yapılmaz. Mail ve eski Raporlar sayfası son fazda kalır.

## Native audit ve sınıflandırma

- `commande/note.php` ayrı native not yazma yüzeyidir; `core/actions_setnotes.inc.php` içindeki `setnote_public` / `setnote_private` eylemleri `CommonObject::update_note` kullanır. Bu rota registry'de eksikti. Public/private notlar rich business text, 65535 dönüşüm sonrası sınır; ortak MaxInput browser/server adapter'ı uygulanır.
- `commande/card.php` create ve `set_incoterms` eyleminin `location_incoterms` alanı business text. `llx_commande.sql` varchar(255); web/API registry'ye text255 eklenir. Incoterms ID/key technical/typed kalır.
- Orders API header POST/PUT notları, POST içindeki `lines[].desc/label`, bağımsız POST/PUT `{id}/lines[/{lineid}]` açıklama/etiketleri mevcut merkezi MaxInput kurallarını kullanır. Rich description65535, label255. Teknik URI/markup attribute case'i korunur. SKU/ref/ref_client/ref_ext, ID/customer/date, fiyat/miktar/vergi/toplam ve enum/token alanları business casing'e girmez.
- Native Orders generic PUT, `location_incoterms` / `fk_incoterms` değerlerini SQL'e yazmaz. Bir API setter icat edilmez; API create ve desteklenen web `set_incoterms` kullanılır. Generic PUT Incoterms güncellemesi desteklenmiş sayılmaz. Bu native sınırlama upstream patch veya özel SQL ile aşılmaz.

## Kabul ve güvenlik

[order_write_http.py](../tests/input/order_write_http.py) yalnız yeni, owned sentetik **taslak** sipariş oluşturur. Gerçek native web login/form, bağımsız native API read ve POST/PUT kullanır; SQL veya session seed'i yoktur. Browser value dönüşümü, JS olmadan doğrudan POST, query-before-body, missing/invalid CSRF ve denied rol test edilir. API anonymous/denied create/header/line/delete reddi, foreign entity create ve foreign customer ilişkisi reddi sınanır. Foreign order GET/PUT/line endpoint entity izolasyonu bu kapsamda doğrulanmış değildir.

Başlık/satır Unicode/Türkçe/NFC/ß genişlemesi, URI/HTML link case'i, exact teknik referans/tarih ve native hesaplanan miktar/fiyat/toplamlar bağımsız okumayla doğrulanır. Label255 ve Incoterms255 tam genişleme sınırı; note/desc65535 ve label/location overflow retleri kesilme/merge olmadan sınanır. API nested create overflow native insert'ten önce reddedilir. Duplicate JSON key reddi korunur.

Native note editorünün bu konfigürasyondaki textarea modu gerçek browser submit ile sınanır. CKEditor etkileşimleri, bütün rich editor varyantları, create ekranı, web satır editörü, clone/bulk/special actions, native ORM/cron/Core-off ve tüm konfigürasyonlar açık/NOT RUN kalır. Fatura/sevkiyat yazma kapsamı bu sipariş adımına dahil değildir. Contacts entity BLOCKED bulgusu ve iki ayrı kurulum kabulü kapanmaz.

## Bütünlük, kanıt ve rollback

Validate/ship/invoice/payment/send çağrılmaz. Yalnız bu koşuda tutulan ID'ler, fixture müşteri, draft statüsü ve exact `OrderWrite-Case` dış referansı doğrulandıktan sonra native Orders DELETE ile temizlenir. Hosted acceptance sonrasında yalnız `actioncomm` tablo hash'inde değişiklik görüldü; kayıt içeriği sınıflandırılmadı. HTTP kabulünün öncesi/sonrası hash kabulü tüm diğer native tablo ve yasal/brand alanlarının aynı kalmasını şart koşar. Test ortak baseline freeze'ından önce çalışır; sonrasında bütün ticari tablolar ve `actioncomm` değişmeden kalmalıdır. Baseline bir kez yazılır, sorgular daraltılmaz. Runner kendi DB/docs/session/credential/process dosyalarını temizler ve upstream hash'lerini karşılaştırır.

Hedefli geliştirme: standart [Windows native komutuna](INPUT_NATIVE_WINDOWS.md) `--http-suite order-writes` eklenir; diğer HTTP aşamaları NOT RUN olur. PR #38 gerçek native HTTP/browser senaryosunu tek kurulumlu hosted Actions akışına da bağlar; terminal assertion ve aynı exact HEAD'de başarılı job olmadan aşama PASS sayılamaz. Bu bilgisayarda Python, Bash ve Docker çalıştırılabilir değil; yerel temel kontroller NOT RUN. `order-write-checks.json` terminal assertion'ı olmadan aşama PASS sayılamaz. Son SHA/sonuç/cleanup `.local/input-native-summary.json`; güvenli whitelist kanıtı `.local/input-evidence-manifest.json`. Raw/config/session/token/log kanıt paketine alınmaz.

İlk dirty geliştirme koşusunda API kontrolleri geçtikten sonra doğrudan `editnote_public` GET'i token taşımadığı için native CSRF 403 verdi. Test native sayfadaki token'lı düzenleme bağlantısını takip edecek şekilde düzeltildi; native güvenlik gevşetilmedi. O koşunun snapshot/cleanup kontrolleri geçti, ancak koşu FAIL'dir ve final kabul sayılmaz.

İkinci hedefli geliştirme koşusunda native kalem bağlantısı hover öncesi gizli olduğu için Playwright click zaman aşımı verdi. Test aynı native bağlantının exact href'ine gider; formun gerçek browser submit kontrolü korunur. Bu ara koşu da final kabul sayılmaz.

Manuel doğrulama: sentetik staging'de taslak siparişin iki notunu ve Incoterms yerini küçük harfle düzenleyip gerçek uppercase değeri ve native API persistence'ını kontrol edin; müşteri referansı/URI/fiyat/miktarları karşılaştırın; denied kullanıcı ve eksik CSRF yazmamalıdır. Staging manuel kontrolü NOT RUN; yerel otomasyon ayrı raporlanır.

Rollback: bu artımın registry/test/runner/fixture değişikliklerini geri alın; not/konum casing açığı geri gelir. Upstream/schema/üretim veri migrasyonu yoktur. Yayın/merge ayrı sahip onayı gerektirir. 25 ürün hedefinin %28 kanıtlı / %72 açık-kısmi hesabı değişmez; bu süre/kod yüzdesi değildir.
