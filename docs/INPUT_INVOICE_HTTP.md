# Native fatura import HTTP kabulü — Issue #36

Başlangıç yerel commit'i `9ba1bb9d0e1446142a3ca2a9ae88518ee3544040`. Mail/çeviri ve eski ASYA Raporlar sayfası son fazda kalır. Bu adım yalnız ücretsiz, disposable yerel Dolibarr 24.0.1 üzerinde sentetik **taslak** fatura importunu doğrular. Fatura validate edilmez, gönderilmez, ödeme alınmaz; GitHub/CI/push/PR/merge/yayın askısı sürer.

## Native sözleşme ve sınıflandırma

`modFacture.class.php` başlık dataset'ini `facture_2`, satır dataset'ini `facture_3` olarak tanımlar. Native bu profilleri BlockedLog modülü açıkken sunmaz. Test fixture'ının mevcut varsayılan konfigürasyonunda profiller mevcuttur; BlockedLog veya herhangi bir yasal güvenlik mekanizması kapatılmaz, descriptor/upstream/schema değiştirilmez. BlockedLog açık konfigürasyonda bu importlar desteklenmiş/kanıtlanmış sayılmaz.

- `f.note_public`, `f.note_private`, `fd.description`: merkezi MaxInput registry'sinde rich business text, dönüşüm sonrası 65535 sınırı.
- `fd.label`: plain business text; gerçek `llx_facturedet.sql` sınırı varchar(255). Native library testi registry ile SQL uyumunu ayrıca denetler.
- Fatura/müşteri referansı, native ilişki anahtarları, tarih, tür, draft/unpaid durumu, para birimi, miktar/fiyat/vergi/toplam ve mapping: teknik/typed.
- Ham CSV/XLSX baytları immutable source; dönüşüm proposal üzerinde yapılır. Prose içindeki URI case'i korunur. Yeni normalizer veya mali hesaplama eklenmez.

## Gerçek HTTP kabulü

[invoice_http.py](../tests/input/invoice_http.py), önceki [sipariş HTTP kabulündeki](INPUT_ORDER_HTTP.md) öğrenilmiş native akışı kullanır. CSV/XLSX upload, public `saveselectorder` mapping, strict_line simülasyonu ve Run Import linki gerçek native sayfalardır; SQL/session seed'i kullanılmaz. Kalıcı sonuç bağımsız Invoices API ile okunur.

Her iki formatta başlık ve satır için:

1. Exact upload baytları, native mapping/simülasyon ve simülasyonun parent/lines kaydını değiştirmemesi.
2. URL step=5 içeren eksik/yanlış token GET/POST: 403 ve değişmeyen kayıtlar. Body-only yanlış-token varyantı: native POST temizliği/default sayfa 200 olabilir; yine yazmamalıdır. Pozitif devamdan önce temizlenmiş mapping public native istekle yeniden kurulur.
3. Native commit linki token'ı URL'ye koymadan POST yapar; API üzerinden Unicode/Türkçe/NFC not/açıklama, exact referans/müşteri/tür/draft/unpaid ve satır miktar/fiyat/toplam kontrol edilir.
4. Label dönüşümü; `ß × 127 + a → SS × 127 + A` ile tam 255 karakter kabulü; `ß × 128 → SS × 128` ile 256 karaktere genişleyen etiketin kesilmeden reddi.
5. Public/private not ve açıklamada `ﬃ × 21846 → FFI × 21846` (65538) overflow. Kaynak hücre XLSX'in 32767 karakter sınırı içindedir. Simülasyon ve doğrudan commit reddedilir; line batch'teki önceki geçerli pending satır da rollback olur.
6. Başlıkta native unique referans çakışması: önceki pending fatura rollback olur, mevcut fatura değişmez/merge edilmez. Satır etiketine/açıklamasına yapay uniqueness kuralı konmaz.
7. Import yetkisiz kullanıcıyla upload/import reddi, yanlış upload CSRF'sinde dosya oluşmaması, son source hash'leri, browser JS/dış istek kontrolü.

## Ticari bütünlük ve temizlik

Başlangıçta referansın bulunmadığı kontrol edilir. Oluşturulan `InvoiceHttp-*` referansları ve commit sonrası native ID'leri tutulur. Temizlik yalnız eşleşen ID/ref, fixture müşterisi, normal fatura türü, draft/unpaid durumu ve sıfır ödeme doğrulanınca native Invoices DELETE API ile yapılır. Protected eski taslak fatura dahil tüm `facture`/`facturedet` ve banka/IBAN/ödeme/stok/sipariş/yasal snapshot kapsamı korunur. Baseline bir kez yazılır; yeni test kayıtları için sorgu daraltılmaz veya baseline yenilenmez.

## Kanıt, yeniden üretim ve kalan iş

[Windows runner](INPUT_NATIVE_WINDOWS.md) yeni `native_invoice_http` aşamasını `invoice-checks.json` terminal assertion'ı ile doğrular; sıfır süreç çıkışı tek başına PASS değildir. Raw hash'ler `invoice-source-hashes.json`; final exact SHA/clean-tree/cleanup `.local/input-native-summary.json` ve `.local/input-evidence-manifest.json` içindedir. Log/raw/config/session/credential kanıt ZIP'ine alınmaz.

Hedefli geliştirme için standart native runner komutuna `--http-suite invoices` eklenebilir; diğer dört HTTP aşaması NOT RUN kalır. Final teslim varsayılan `--http-suite all` üzerinde tüm native gruplar, aynı HEAD yerel kontroller ve temizliği gerektirir. Test kodunu geri almak upstream/schema/üretim verisini etkilemez.

Final PASS ile mevcut yedi registry profilinin **7/7 CSV/XLSX insert/strict_line HTTP** altkapsamı kanıtlanır. Bu, tüm import modları/konfigürasyonlar veya global uppercase uyumu değildir: update-key/fast_bulk, kalan web/API/rich editor, ORM/cron/third-party ve Core kapalı yollar ayrı açık/BLOCKED kapsamdır. Docker/ortak 63, iki kurulum izolasyonu ve release kabulü bu Windows koşusunun dışında kalır. Eski 25 ürün hedefinin %28 kanıtlı / %72 açık-kısmi hesabı değişmez.
