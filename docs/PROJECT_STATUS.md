# MAX — proje durum raporu, 7 Kasım 2026

**Bulunduğumuz yer: fonksiyonel modül MVP'leri ve yerel entegrasyon sertleştirmesi. Ürün yayına hazır değildir.** Native ERP tabanı, Core marka/kimlik ayrımı, sınırlı Mobile, Orders 0.2, Customs ve read-only Mail intake kaynakları var. Şu an Issue #36 zorunlu Türkçe büyük harf politikasının gerçek native giriş kabullerini kapatıyoruz. Sipariş notları/Incoterms altkabulü `codex/max-order-writes` dalında, draft PR #37 üstünde yığılmıştır; remote `main` ve PR durumu public API üzerinden kontrol edilmiştir. PR #34 içeriği bu dala alınmadı. Mail ve eski Raporlar işleri son fazda kalır.

## Yüzde hesabı

Mevcut [çapraz modül kabul matrisindeki](specs/ACCEPTANCE_MATRIX.md) **25 senaryoyu** eşit ağırlıkla, [izlenebilir durum listesine](PROJECT_STATUS.json) eşledim:

| Durum | Senaryo | Pay |
|---|---:|---:|
| Geliştirme altkapsamı için kanıtı bulunan | 7 | %28 |
| Kısmi; kapanmış sayılmayan | 7 | %28 |
| Uygulanmamış | 10 | %40 |
| Kurulumlar arası doğrulanmamış | 1 | %4 |
| **Açık veya kısmi toplam** | **18 / 25** | **%72** |

**Raporlanabilir kalan oran: kabul kapsamının %72'si açık.** Bu bir süre/maliyet veya kalan kod miktarı tahmini değildir. Ağırlıkları ürün sahibiyle belirlenmemiş taslak kabul matrisi kullanılır; HKS bu paydanın içindedir. Yedi kanıtlı kalem, mevcut depo testleri ve kayıtlı eski native kanıtlarla eşleştirilmiş geliştirme altkapsamlarıdır. Güncel HEAD için tüm ürünün yeniden kabulü veya üretim onayı sayılmaz. Sonraki raporlarda aynı 25 ID korunmalı; payda değişirse gerekçesi kaydedilmelidir.

Issue #36 büyük harf normalizasyonu, bu eski matris dışında eklenen engelleyici kalite kapısıdır. Cari/kişi testlerinin tamamlanması eski 25 hedefin yüzdesini yapay olarak artırmaz; native CSV/XLSX insert/strict_line HTTP altkapsamı yedi profile ulaştı; tüm giriş yüzeyleri ayrıca izlenir. Matrisin tamamı kapansa bile bu yeni kalite kapısı ve staging onayları ayrıca tamamlanmalıdır.

## Modül bazında konum

| Bölüm | Eldeki iş | Kapanacak ana işler |
|---|---|---|
| Altyapı / native ERP | Pinned Dolibarr, native ticari fixture'lar, ücretsiz disposable yerel kurulum, kaynak audit'i | Üretim ortamı, restore provası, sürüm/dağıtım kabulü |
| Core / Mobile | Marka-yasal kimlik ayrımı, yetkiler, beş native listeye sınırlı responsive destek | İki ayrı kurulum kabulü, fiziksel telefon/erişilebilirlik, tüm ürün rol regresyonu |
| Orders | Manuel PO/LL/PC paket/revizyon/kilit/çakışma; private PDF/XLSX intake ve sınırlı XLSX önerileri | Otomatik PDF/OCR, genel/çok ürünlü şablonlar, etkin miktar/öncelik kararı, Mail köprüsü; #34 uzlaştırması |
| Mail | Çok hesaplı read-only IMAP intake, UID dedup/retry/quarantine, mailbox scope | SMTP/outbox/onay, çeviri, gerçek provider/TLS/OAuth kabulü, downstream köprüler |
| Customs | Yerel taslak/statü/geçmiş, ERP linkleri, hassas alan maskeleme | Genel belge parser'ı, HKS draft köprüsü ve domain kabulü |
| HKS | Teknik taslak şartname | Uygulama, mock SOAP, katalog/validator, eşzamanlılık ve belirsiz sonuç yönetimi |
| Özel raporlar | Native-first şartname, native export tabanı | Kabul edilmiş özel rapor alanları, XLSX çıktıları ve formula injection regresyonları |
| Bütün ürün | Önceki main'de tarihsel ortak 63 native kabul kaydı | Bu input HEAD'inde ortak 63, tüm yeni giriş yüzeyleri, uçtan uca Mail→Orders→Customs→HKS |
| Staging / canlı | Runbook ve sınırlar | Host/DNS/TLS/erişim, backup/restore, lisans ve sahip dağıtım onayı; henüz deployment yok |

## Güncel öncelik ve bu iş kolu

Sahibinin 6 Kasım talimatıyla **Mail sayfası, çeviri ve bütün Mail geliştirmeleri/köprüleri son faza, gerekirse canlı sonrasına** taşındı. Eski ASYA **Raporlar sayfasındaki** çıktılar da yeniden geliştirme ihtimaliyle son fazda. Diğer ekranlardan gelen raporlar kaynak ekranı, alanları ve native karşılığı doğrulanınca aktif aday olabilir. Güncel ASYA sistemine bu işte erişilmedi; adayların kökeni doğrulanmış sayılmaz.

Bu erteleme 25 kabul hedefini silmez: **%28 kanıtlı altkapsam / %72 açık-kısmi** aynı kalır. Mail'e bağlı INT-01/03 son fazda; ORD-05'in bağımsız belge dedup kısmı çalışmaya devam eder. RPT-01/02'nin hangi eski ekrana dayandığı henüz doğrulanmadığından hepsi otomatik ertelenmiş veya aktif sayılmaz. İlerlemenin değişmesi için testle kabul kapanması gerekir; öncelik değiştirmek yeterli değildir.

Önceki ürün/cari/kişi/sipariş **5/7 profil (%71,4)** CSV/XLSX insert/strict_line HTTP kabulüne [fatura başlık/satır kabulü](INPUT_INVOICE_HTTP.md) eklenir. Yeni `native_invoice_http` aşaması not/açıklama ve 255 karakter label dönüşümü, gerçek native commit/API okuması, rol/CSRF, collision/overflow batch rollback ve yalnız owned sentetik draft ID'lerinin native API ile temizliğini sınar. İlk ticari snapshot bütün fatura/satır tablolarını korumaya devam eder; sorgular daraltılmaz.

Final exact clean HEAD üzerinde tüm aşamalar PASS olduğunda mevcut registry'nin **7/7 profili (%100)** için bu dar HTTP altkapsamı tamamlanır. Fatura profilleri native BlockedLog açıkken sunulmaz; test mevcut varsayılan fixture konfigürasyonuna aittir, bir yasal modül devre dışı bırakılmaz. Bu oran tüm konfigürasyonlar/import modları veya global giriş uyumu değildir. Update-key/fast_bulk, kalan web/API/rich editor ve ORM/cron/third-party sınırları açık/BLOCKED kalır. Final SHA/sonuçlar `.local/input-native-summary.json` ve `.local/input-evidence-manifest.json` içindedir.

Bu devam işi [cari/kişi web/API kabulünü](INPUT_CRM_HTTP.md) ekler: eksik contact public/private not kuralları tamamlanır, cari town sınırı gerçek SQL ile 64→50 düzeltilir. API create/update ve native browser create/edit, doğrudan POST, rol/CSRF, cari entity=2 read/update reddi ve Contacts entity filtresi için ayrı BLOCKED native bulgu ve desteklenen trigger/native ORM ile e-posta case koruması, uzunluk sınırları ve owned temizliği ayrı `native_crm_http` aşamasında izlenir. Entity=2 müşteri/kişi de immutable snapshot'a eklenir. Tüm CRM aksiyonları veya bütün API kaynakları tamamlanmış sayılmaz.

7 Kasım devam işi [depo web/API metadata kabulünü](INPUT_WAREHOUSE_HTTP.md) ekler. Eksik lieu alanı 64 sınırıyla sınıflandırılır, town gerçek SQL'e göre 50'ye düzeltilir. Yeni boş depolarla native API/browser create/update, rol/CSRF/entity, post-conversion sınır/unique collision ve boşluk doğrulamalı native cleanup sınanır. Bütün entrepot tablosu immutable snapshot'a eklendi; stoklu mevcut depo dahil hiçbir eski satır değişemez. Final native sonuç ayrı `native_warehouse_http` aşamasında izlenir.

Sırada order/invoice/shipment web/API write ve rich editor/özel aksiyon açıkları vardır. Önceki Contacts entity sınırı BLOCKED, iki kurulum izolasyonu ve ortak Docker 63 NOT RUN kalır. Eski 25 ürün hedefinin **%28/%72** hesabı aynı kalır.

7 Kasım sipariş yazma artımı [ayrı native not rotası ve Incoterms teslim yerini](INPUT_ORDER_WRITES.md) sınıflandırır. Yeni `native_order_write_http` aşaması sentetik taslak sipariş header/nested line ve bağımsız line API yazımları, gerçek browser not gönderimi, JS-less not/Incoterms POST, rol/CSRF/query-precedence ve dönüşüm sonrası overflow sınırlarını bağımsız API okumalarıyla sınar. Native generic Orders PUT Incoterms alanlarını saklamaz; bu uç için destek iddia edilmez. Fatura/sevkiyat yazma ve web satır/rich editor/special-action kapsamı sonraki işlerdir. Exact final HEAD ve sonuçlar yerel kanıt manifestinde tutulur; bu artım eski 25 hedefin yüzdesini değiştirmez.

## Öncelik sırası

1. Zorunlu MaxInput native import/web/API açıklarını kapatmak; ardından aynı HEAD ortak güvenlik/regresyon kabulleri.
2. Mail'den bağımsız Orders öncelik/miktar kuralları, belge/revizyon akışı ve 4+6 sevkiyat kabulü; PR #34 ayrı uzlaştırılacak.
3. Customs'ın manuel/native bağlantıları ve hedef teslimata dahilse HKS draft/mock güvenliği.
4. Eski Raporlar sayfası dışındaki operasyonel çıktıların kaynak envanteri ve native-first değerlendirmesi; kanıtlı boşluklar için export.
5. İki kurulum izolasyonu, fiziksel cihaz/erişilebilirlik, güvenlik/lisans, backup/restore ve staging hazırlığı; canlıya alma ayrı onaya bağlı.
6. **En son:** Mail/çeviri/gönderim/köprüler ve eski Raporlar sayfasının olası yeniden tasarımı. Kritik bağımlılık somutlaşırsa etkisi ve minimum çözümü ayrıca ele alınır.

Tam bağımlılık ve kabul kapıları [ROADMAP.md](ROADMAP.md) içindedir. Windows loopback kanıtı Docker ağ/kimlik izolasyonu yerine geçmez. Erteleme, uygulanan mevcut yüzeylerin güvenlik ve veri bütünlüğü yükümlülüklerini kaldırmaz.

GitHub Actions/push/yeni PR, billing değişikliği, merge ve deployment askısı aynen geçerlidir. Eski CI seçim testinin NTFS satır-sonlu filename fixture hatası, Docker/WSL eksikliği ve henüz çalıştırılmayan kabuller gizlenmez. Gerçek müşteri/posta hesabı/HKS veya ücretli servis kullanılmaz.
