# MAX — proje durum raporu, 6 Kasım 2026

**Bulunduğumuz yer: fonksiyonel modül MVP'leri ve yerel entegrasyon sertleştirmesi. Ürün yayına hazır değildir.** Native ERP tabanı, Core marka/kimlik ayrımı, sınırlı Mobile, Orders 0.2, Customs ve read-only Mail intake kaynakları var. Şu an Issue #36 zorunlu Türkçe büyük harf politikasının gerçek native giriş kabullerini kapatıyoruz. Çalışma yerel `feat/max-uppercase-inputs` dalında; remote main/PR durumunu güncel sayan bir sorgu yapılmadı. PR #34 içeriği bu dala alınmadı.

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

Issue #36 büyük harf normalizasyonu, bu eski matris dışında eklenen engelleyici kalite kapısıdır. Cari/kişi testlerinin tamamlanması eski 25 hedefin yüzdesini yapay olarak artırmaz; native HTTP import altkapsamı 1/7'den 3/7 profile ilerler. Matrisin tamamı kapansa bile bu yeni kalite kapısı ve staging onayları ayrıca tamamlanmalıdır.

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

## Bu raporun iş kolu

`produit_1` CSV/XLSX HTTP wizard'ı önceki yerel commit'te kanıtlandı. Bu devam işi `societe_1` müşteri/tedarikçi ve `societe_2` kişi profillerini native HTTP yükleme, eşleme, simülasyon, token/rol, post-conversion sınırı ve rollback ile doğrular. Tam sonuç ve exact SHA [cari/kişi import kaydında](INPUT_PEOPLE_IMPORT.md) ve ignored `.local/input-native-summary.json` içinde tutulur. Her profile ait CSV ve XLSX insert/strict_line altkapsamı geçerse registry'deki **3/7 profil (%42,9)** için bu dar HTTP kanıtı bulunur; diğer **4/7 (%57,1)** profil NOT RUN kalır. Bu oran web/API/ORM/cron dahil global büyük harf uyumu değildir.

## Öncelik sırası

1. Aynı zorunlu MaxInput sınıflandırmasıyla kalan dört import profilini ve native web/API açıklarını kapatmak; desteklenmeyen ORM/cron/third-party yollarını ayrıca ele almak.
2. Ücretsiz Docker-capable yerel ortamda aynı HEAD üzerinde ortak 63 ve bütün modül güvenlik/regresyonunu çalıştırmak. Windows loopback kanıtı private Docker ağı/kimliği yerine kullanılamaz.
3. Ürün kararı gerektiren Orders önceliği/şablonlar ve rapor alanlarını kesinleştirmek; PDF/Mail köprüleri ve SMTP/approval/translation işlerini uygulamak.
4. HKS mock/draft entegrasyonu ve tam modül köprülerini tamamlamak; canlı dış etki yalnız ayrı yetkiyle.
5. Fiziksel cihaz, erişilebilirlik, güvenlik/lisans, backup/restore ve staging onaylarını kapatmak.

GitHub Actions/push/yeni PR, billing değişikliği, merge ve deployment askısı aynen geçerlidir. Eski CI seçim testinin NTFS satır-sonlu filename fixture hatası, Docker/WSL eksikliği ve henüz çalıştırılmayan kabuller gizlenmez. Gerçek müşteri/posta hesabı/HKS veya ücretli servis kullanılmaz.
