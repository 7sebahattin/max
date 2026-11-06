# Native sipariş import HTTP kabulü — Issue #36

Başlangıç yerel commit'i `4d3cf82d61a068962ac89bd5b05218e7a750b2b2`. Mail ve eski ASYA Raporlar sayfası son fazda kalır. Bu adım, önceki [dataset/alan düzeltmesini](INPUT_ORDER_IMPORT.md) gerçek native upload → mapping → simulation → commit akışında sınar. Upstream ve ticari snapshot kapsamı değiştirilmez; GitHub/CI/yayın askısı korunur.

## Sınıflandırma ve uygulama

[order_http.py](../tests/input/order_http.py), mevcut kaynak üreticisini kullanır. `commande_1` public/private notları ve `commande_lines_2` açıklaması merkezi registry'deki rich business alanlarıdır. Sipariş/müşteri referansları, native foreign key, tarih, durum, miktar, fiyat, vergi ve toplamlar teknik/typed kalır. Açıklama içindeki URI'nin case'i korunur; CSV/XLSX ham baytları immutable source'tur. Yeni bir normalizer, gölge sipariş veya mali hesaplama eklenmez.

İki format ve iki profil için gerçek native formlar, public mapping POST'u, strict_line simülasyonu ve Run Import linki kullanılır. SQL yazımı veya session mapping seed'i yoktur. Sonuçlar bağımsız native Orders API üzerinden okunur.

## Kabul kapsamı

- Gerçek upload ve saklanan exact kaynak baytları; eşleme ve simülasyon formları.
- Simülasyonun parent/line kaydını değiştirmemesi; URL'de step=5 bulunan token'sız/yanlış token GET/POST'un 403 ile yazmadan reddi. Yalnız body'de step bulunan yanlış-token POST'ta native güvenlik POST'u temizleyip varsayılan sayfayı 200 döndürebilir; bu varyantta da kayıtların değişmediği ayrıca sınanır.
- Native commit linkinin token'ı URL'ye koymadan POST yapması; public/private not, açıklama, Türkçe/NFC/Unicode genişlemesi; teknik referans/URI ve miktar/fiyat/toplam korunması.
- Body-only yanlış-token isteği native başlangıç sayfasına döndüğünde session mapping'i temizleyebilir. Pozitif kabulden önce mapping, native public `saveselectorder` isteğiyle yeniden kurulur; session seed'i yapılmaz. Simülasyon linki tek başına başarı kanıtı sayılmaz; gerçek persistence API üzerinden zorunlu okunur.
- Sipariş referans çakışmasında batch rollback, önceki pending siparişin kalmaması ve mevcut kaydın birleştirilmemesi.
- Her iki header notunda ve line açıklamasında `ﬃ × 21846 → FFI × 21846` ile 65535 sınırının aşılması: simülasyon ve doğrudan commit reddi. Ham hücre 21846 karakterdir; XLSX'in 32767 hücre karakter sınırı içinde kalır, ancak dönüşüm sonrası 65538 karakter oluşur. Line batch'teki önceki geçerli pending satırın da rollback olması. Line açıklaması unique sayılmaz; yapay duplicate kuralı konmaz.
- Import yetkisiz kullanıcının geçerli token ile upload/import reddi; yanlış upload CSRF'sinin dosya bırakmaması.
- Son raw dosya hash kontrolü, browser JavaScript hatası ve dış browser isteği olmaması.

## Ticari bütünlük ve temizlik

Pozitif HTTP commit testi yalnız disposable kurulumda yeni `OrderHttp-*` sentetik **taslak** siparişler oluşturur. Her siparişin referansı, fixture müşteri bağlantısı ve draft durumu doğrulanmadan temizlik yapılmaz. Yalnız bu koşunun tuttuğu referanslar native Orders DELETE API ile kaldırılır; bağlı satırlar da native servis tarafından kaldırılır. Sipariş validate edilmez; sevkiyat, fatura, ödeme veya gerçek bildirim üretilmez.

Bu yöntem pozitif persistence kanıtını, sabit ticari fixture'lardan ayırır. Bütün `commande` ve `commandedet` dahil ilk before/son after snapshot aynen karşılaştırılır; yeni test kayıtlarını dışlamak için sorgular daraltılmaz ve baseline yenilenmez. Test/temizlik başarısızlığı PASS sayılmaz; ana runner ayrıca owned DB/docs/session/credential/process temizliğini uygular.

## Kanıt ve kalan kapsam

[Windows runner](INPUT_NATIVE_WINDOWS.md) yeni `native_order_http` aşamasını çalıştırır. `order-checks.json` içindeki terminal assertion olmadan, PHP/HTTP sıfır çıkış kodu yeterli sayılmaz. Raw hash'ler `order-source-hashes.json`, exact HEAD/clean-tree, aşama ve cleanup sonucu `.local/input-native-summary.json` içindedir. Ara/dirty koşular teslim kanıtı değildir.

Bu aşama final clean HEAD üzerinde PASS olduğunda dar CSV/XLSX insert/strict_line HTTP kapsamı **3/7 → 5/7 (%71,4)** olur. Fatura başlık/satır **2/7 (%28,6) HTTP NOT RUN** kalır. Update-key/fast_bulk, tüm native web/API, rich editor, ORM/cron/third-party ve Docker ortak 63 kapsamı bu teste dahil değildir. Bu ilerleme eski 25 hedefteki %28 kanıtlı / %72 açık-kısmi oranını değiştirmez; uppercase kalite kapısı ayrı izlenir.

Yeniden üretim komutu `INPUT_NATIVE_WINDOWS.md` içindekiyle aynıdır. Rollback: yerel commit'i geri almak yalnız ek test/runner/dokümanları kaldırır; upstream/schema veya üretim verisi değişikliği yoktur. Yayın/merge/push yapılmaz.

Hızlı geliştirme için aynı komuta `--http-suite orders` eklenebilir. Bu koşu da installer/fixture ve son importer/lifecycle/snapshot kontrollerini çalıştırır; diğer üç HTTP aşaması açıkça NOT RUN kalır. `http_suite: orders` sonucu tam regresyon kabulü sayılmaz. Final teslim için varsayılan `--http-suite all` ve tüm aşamaların PASS olması gerekir.
