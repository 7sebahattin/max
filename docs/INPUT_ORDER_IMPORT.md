# Sipariş import eşlemesi — Issue #36

6 Kasım 2026: Mail/çeviri ve eski ASYA Raporlar sayfası son faza taşınırken bağımsız zorunlu input bütünlüğü işi sürdürülür. Başlangıç yerel HEAD `31f6abfb3ef203931cdb991d1ab4bd31ce9855ac`. GitHub/CI/yayın askısı değişmez; yalnız sentetik yerel disposable kurulum kullanılır.

## Hata ve düzeltme

Pinned Dolibarr 24.0.1 `modCommande.class.php`, sipariş satırlarını ikinci dataset olarak `commande_lines_2` ve tablo alias'ı `cd` ile tanımlar. Registry'deki `commande_lines_1 / d.description` gerçek native isteğe eşleşmiyordu: hook bilinmeyen dataset için native davranışa döndüğünden açıklama normalizasyonu atlanabilirdi. Registry gerçek `commande_lines_2 / cd.description` ile düzeltildi. Upstream değiştirilmedi.

Yedi registry profilinin tamamı gerçek native `Import::load_arrays()` sonucu ve alan alias'ları ile karşılaştırılır. Native ürün descriptor'ında bulunmayan `p.note_private` kaldırıldı; private notun gerçek native alanı `p.note` aynen korunur. Web/API private not kuralları değişmez.

## Alan sınıflandırması ve kabul

- `commande_1`: public/private notlar rich business text; referans, şirket foreign key, tarih ve durum teknik/typed.
- `commande_lines_2`: açıklama rich business text; sipariş referansı/bağlantısı, miktar, ürün tipi teknik/typed. HTML içindeki URI exact korunur.
- CSV/XLSX baytları ve okuyucunun typed ham satırı immutable source. Dönüşüm sadece proposal kopyasında yapılır.
- [order_import_native.php](../tests/input/order_import_native.php), owned CLI fixture'ın verify aşamasına dahildir. Her profil × CSV/XLSX için gerçek native okuyucu, HookManager ve importer ile insert yapılır, transaction içindeki değer SQL ile bağımsız okunur, ardından outer simulation transaction rollback edilir. Sipariş başlığı/satırı kalıcı olarak değişmez; tüm ticari tabloları kapsayan ilk before/son after snapshot kontratı daraltılmaz.
- Türkçe/Unicode genişlemesi, not/açıklama dönüşümü, referans/URI/miktar bütünlüğü, ham dosya/satır bütünlüğü ve rollback sınanır. Profil başına HTTP upload/mapping/rol/CSRF gerçek commit kabulü bu yeni CLI testinin kapsamında değildir.

## Yeniden üretim ve kanıt sınırı

[Windows native runner](INPUT_NATIVE_WINDOWS.md) komutu aynı kalır. Yeni kontroller `native_import_lifecycle_snapshot` aşamasındaki `verify-checks.json` içine yazılır. Exact HEAD, clean-tree, aşamalar ve cleanup sonucu `.local/input-native-summary.json` içindedir; ara geliştirme sonuçları final SHA kanıtı sayılmaz.

HTTP kapsamı ürün/cari/kişi için 3/7 profildir. Sipariş başlık/satır HTTP kabulü, fatura başlık/satır, update-key/fast_bulk, genel ORM/cron yüzeyleri açık kalır. Zorunlu uppercase engeli ve eski 25 ürün kabul hedefi ayrı izlenir; bu eşleme düzeltmesi tek başına ürünün tamamlanma yüzdesini artırmaz. Ortak 63/Docker, gerçek SMTP/HKS ve deployment bu yerel koşuyla kanıtlanmaz.
