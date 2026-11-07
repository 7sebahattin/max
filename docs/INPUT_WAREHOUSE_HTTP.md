# Depo web/API yazma kabulü — 7 Kasım 2026, Issue #36

Başlangıç yerel HEAD `c05d2a959980a8fd9be6c2f5d20a3524e11fdd6a`. Mail/çeviri ve eski ASYA Raporlar sayfası son fazda; CI/push/yeni PR/merge/yayın askısı sürer. GitHub Issue #1 için salt-okunur web erişimi 404 döndü, GitHub CLI bulunamadı; bu iş güncel AGENTS.md, yerel PRODUCT_SCOPE ve sahibinin Issue #36 kapsamında sonraki işe devam talimatına dayanır. Issue #1 içeriği yeniden okunmuş sayılmaz.

## Native audit ve merkezi düzeltmeler

- `product/stock/card.php`: `libelle` → native warehouse label, `desc` → description; `lieu` konum özeti. API resource `warehouses` aynı native Entrepot nesnesini kullanır.
- `llx_entrepot.sql`: label'ın saklandığı `ref` varchar(255), lieu varchar(64), address varchar(255), town varchar(50). Konum özeti web/API registry'ye `text,64` eklendi; town 64→50 düzeltildi. Native library testi iki girişin kurallarını pinned SQL ile eşler.
- Label/libelle, lieu, address, town business text; description/desc rich business text. ID/parent/country/status/usage, zip/phone/fax/barcode ve token teknik/opaque. Native depo label'ı SQL `ref` kolonunda saklanır; bu kullanıcıya görünen depo adıdır, ayrı case-sensitive ürün SKU'su değildir. API PUT `ref` deprecated olduğu için native 400 ret davranışı korunur.
- Upstream, SQL schema, stok algoritması ve mali hesaplama değiştirilmez. Ortak MaxInput adapter'ı kullanılır; ekran başına casing kodu veya kullanıcı kapatma ayarı eklenmez.

## Gerçek native kabul

[warehouse_http.py](../tests/input/warehouse_http.py): gerçek admin/denied web login ve bağımsız anonymous context, native warehouse API create/update/read/delete ve browser create/edit formu. SQL/session seed'i yoktur.

1. API create persistence: Türkçe/NFC/Unicode genişlemesi, konum/adres/şehir/açıklama ve prose/HTML içindeki URI case'i; teknik zip/phone/barcode ve typed durum/parent.
2. API label255/lieu64/town50/address255 tam post-conversion sınır kabulü; bir sonraki ß genişlemesinde update/create için 400 ve önceki kaydın korunması. Açıklama 65535 sınırı için geçerli boyutlu source string'in 65538 karaktere genişlemesi reddedilir.
3. Post-conversion unique label çakışması: native unique `(ref,entity)` ret verir, mevcut kayıt merge edilmez/değişmez. Duplicate JSON key ve native deprecated ref update reddedilir.
4. API auth/role create/update/delete reddi; entity=2 depo GET/PUT/DELETE reddi. Bu tek kurulum içi kontrol, iki ayrı müşteri kurulumu kabulü değildir; önceki Contacts entity BLOCKED bulgusunu kapatmaz.
5. Browser edit/create alanlarının gerçek value'su uppercase; gerçek native browser submit ve bağımsız API persistence. JS çalıştırmayan doğrudan update POST, query-before-body, eksik/yanlış CSRF, denied rol ve web overflow kontrolleri.

## Stok sınırı ve temizlik

Testler yalnız yeni, boş sentetik depoları değiştirir; stok hareketi, miktar düzeltmesi, lot, ürün tahsisi, fatura veya ödeme endpoint'i çağrılmaz. Native depo DELETE stok tablolarını da silebildiği için yalnız bu koşuda oluşturulan ID'ler temizlenir; öncesinde aktif entity ve native `warehouses/{id}/products` üzerinden boşluk doğrulanır. Browser/API label lookup'ları başlangıçta boş olmalıdır.

Fixture'a entity=2 boş depo eklendi. Immutable ticari snapshot **bütün entrepot tablosunu da** içerir; böylece korunan stoklu depo metadata'sı ve foreign depo dahil tüm satırlar son kontrolde aynı olmalıdır. Stock/product_batch/stock_mouvement ve para/sipariş/fatura/ödeme/yasal sınırların önceki kapsamı korunur. Baseline bir kez alınır, daraltılmaz veya test sonrasında yenilenmez.

## Kanıt ve kalan kapsam

Windows runner `native_warehouse_http` aşamasında `warehouse-checks.json` terminal assertion'ı olmadan PASS vermez. Hedefli geliştirme: standart komuta `--http-suite warehouses`; diğer HTTP grupları NOT RUN kalır. Final teslim aynı clean HEAD'de varsayılan `--http-suite all`, yerel kontroller ve owned cleanup ile doğrulanır. Son exact SHA/sonuçlar `.local/input-native-summary.json` ve `.local/input-evidence-manifest.json` içindedir; raw/log/config/session/credential ZIP'e girmez.

Bu kapsam depo metadata girişleridir; stok hareketleri, parent/hierarchy özel aksiyonları, tüm rich editor etkileşimleri, bütün konfigürasyonlar veya global uppercase uyumu değildir. Sipariş/fatura/sevkiyat write yüzeyleri, update/bulk import, ORM/cron/Core-off, Contacts entity sınırı ve Docker ortak 63 ayrı açık/BLOCKED kalır. 25 ürün hedefinin %28 kanıtlı / %72 açık-kısmi hesabı değişmez. Rollback: bu registry/test/runner/fixture commit'ini geri almak eski lieu/town açığını geri getirir; üretim veri/schema migrasyonu yoktur.
