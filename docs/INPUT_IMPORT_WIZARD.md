# Native HTTP import wizard — Issue #36

Bu devam çalışması `ed2a3b52d8e74b00bb7f43eba01845634e68b57a` üzerine, aynı yerel `feat/max-uppercase-inputs` dalında uygulanır. Sahibinin “sonraki işe geçelim” talimatı, önceki teslimde önerilen HTTP wizard rol/CSRF/rollback işini yetkilendirir. 8 Kasım owner update scoped draft PR ve standart hosted Actions'ı yetkilendirdi; billing, merge ve deployment kısıtları sürer. Native çekirdek değiştirilmez.

## Giriş sınıflandırması

| Alan/yüzey | Sınıf ve uygulama |
|---|---|
| `imports/import.php` → `import_name` | Business text; registry `text,50` (native `import_model.label varchar(50)`); gerçek input ve GET/POST sunucu değeri merkezi MaxInput ile dönüşür |
| `produit_1` → `p.label` | Business text; mevcut native CSV/XLSX ImportInsert adaptörü ve dönüşüm sonrası 255 karakter sınırı |
| `p.ref`, `p.url`, fiyat/sayısal literaller, tip/status/VAT/HT enum'ları | Technical/typed; normalizer bunları dönüştürmez. Kaynak literalinin exact korunması ayrı hash ile kontrol edilir; native DB'nin fiyat saklama biçimi native kontrattır |
| `token`, `step`, `action`, `format`, mapping, `filetoimport`, dosya adı, importid | Opaque taşıma/işlem değerleri; büyük harf dönüşümü yok |
| Upload CSV/XLSX baytları ve hücrelerin raw değerleri | Immutable source; browser veya profil normalizasyonu kaynak dosyaya uygulanmaz |

Merkezi registry artık **20 web route ailesi / 7 API kaynağı / 7 import profili** içerir. Bu sayılar tüm yüzeylerin native kabulü değildir.

## Native inceleme ve uygulanan düzeltmeler

Pinned Dolibarr 24.0.1 `imports/import.php` gerçek kayıt işlemini `step == 5` ile başlatır. Native simülasyonun ürettiği Run Import bağlantısı GET'tir; `action` veya token taşımaz. Native genel CSRF kontrolünün token gerektiren POST/action yollarına güvenerek bu işlemi açık bırakmak kabul edilmedi.

- MAX Core `updateSession` hook'u bu route'ta step 5 için native `currentToken()` ile exact token doğrulaması yapar. Kontrol, native ile aynı sayısal step karşılaştırmasını kullanır (`5.0` gibi eşdeğer değerler de korunur). Eksik, yanlış veya yanlış tip token reddedilir; raw kaynak okunmadan önce HTTP 403 verilir.
- `ImportInsert` hook'u ayrıca authoritative row sınırında kontrol eder. Böylece erken oturum hook'unun farklı login branch'inde çalışacağını varsaymaz. Tanınmayan dataset fallback'i de CSRF kontrolünü atlayamaz. Native authentication, import.run yetkisi ve transaction kontrolleri devam eder.
- Merkezi browser adaptörü, aynı origin/path üzerindeki native step-5 linkinin parametrelerini değiştirmeden bir POST formuna taşır ve mevcut native meta token'ını ekler. Token URL'ye yazılmaz. Script kapalı/çalışmıyorsa token'sız GET reddedilir; güvenlik kapatılmaz. URL'de geçerli native token taşıyan doğrudan çağrı için native yöntem davranışı ayrıca değiştirilmez.
- Native gerçek import, hata durumunda outer transaction'ı geri alır; son ekranda yalnız sayaçlar bulunabilir. MAX, ImportInsert reddini request-local state ile kaydeder ve desteklenen `beforeBodyClose` hook'undan erişilebilir `role=alert` mesajı ekler: **bu partide hiçbir kayıt kaydedilmedi**. Mesaj sonuç alanına taşınır; native sayaçlar ve transaction kodu değiştirilmez. Başarılı partide hata mesajı gösterilmez. Mesaj EN/TR çevrilidir, SQL/source/credential içermez.

İlk geliştirme koşularındaki eksik format aktarımı ve minimum XLSX fixture'ın eksik styles.xml tanımı test harness'inde düzeltildi. Bunlar upstream patch veya gevşetilmiş native kabul koşulu değildir. Eksik aşama/terminal assertion bulunan koşu PASS sayılamaz; wizard kendi ayrı manifest'ini gerektirir.

## Gerçek HTTP kabulünün kapsamı

[wizard_http.py](../tests/input/wizard_http.py), mevcut [native runner](../scripts/input_native_local.py) içinde, aynı disposable DB/HTTP kurulumunda çalışır. Native UI üzerinden dosya yükler; public native mapping isteğini gerçek oturum/token ile gönderir; native simülasyon butonu ve gerçek Run Import linkini kullanır. SQL veya session mapping seed'i yoktur. DB'deki sonuçlar bağımsız native Products API ile okunur.

CSV ve XLSX için kontroller:

1. Upload baytları, mapping formu ve simülasyon formu gerçekten mevcuttur; HTTP 200 tek başına başarı sayılmaz.
2. Profil adı browser actual-value, doğrudan POST persistence ve dönüşüm sonrası overflow testinden geçer.
3. Türkçe i/ı, decomposed NFC ve ß genişlemesi import sonunda doğru label üretir. Case-sensitive SKU/URL ve native fiyat değeri korunur.
4. Simülasyondan sonra ürün DB'de yoktur. Native Run Import linki token'ı URL'ye koymadan POST gönderir ve beklenen ürün oluşur.
5. Token'sız GET, yanlış token GET/POST ve token'sız POST ürünü kaydedemez. Yanlış upload token'ı yeni source dosyası bırakamaz.
6. Sonraki satır native unique SKU'ya çarptığında önceki başarılı pending satır da rollback olur; var olan ürünün label'ı değişmez. UI simülasyon gate'i bypass edilerek aynı koşul doğrudan gerçek HTTP commit üzerinde de sınanır. Hata mesajı doğrulanır.
7. Genişleme sonucu 255 karakteri aşan label, simülasyon ve gerçek importta kesilmeden reddedilir; DB'de kayıt yoktur.
8. Gerçek native token'ı olan fakat import.run yetkisi olmayan kullanıcı, yükleme ve doğrudan step-5 çağrısında native “Access denied” cevabı alır; dosya/ürün oluşmaz. Native access-denied sayfasının HTTP 200 dönebildiği ayrı tutulur; 200 yetki başarısı sayılmaz.
9. Yüklenen sekiz source'un son hash'leri ilk upload baytlarıyla aynıdır. Browser JS hatası ve dış isteği başarısızlık sayılır.

Wizard'dan sonra önceki native importer/lifecycle/outer transaction kontrolleri ve aynı sabit ticari before/after snapshot doğrulaması çalışır. Baseline yenilenmez. Yeni test ürünleri ve profil kayıtları izin verilen test çıktılarıdır; önceden dolu banka/IBAN/stok/sipariş/draft fatura ve legal sabitler değişemez. Dolu invoice-payment/lot/shipment kanıtı bu görevle eklenmiş değildir.

## Yeniden üretim ve kanıt

Geliştirme koşusu: **101 wizard assertion PASS**, önceki **37 fixture + 50 native HTTP/browser + 29 import/lifecycle/snapshot PASS** ile aynı disposable kurulumda tamamlandı. Sekiz upload hash'i korundu; ticari before/after eşleşti; süreç/DB/credential temizliği ve upstream hash kontrolü PASS. Bu dirty-tree geliştirme kaydı, teslim commit'inin exact SHA kanıtı yerine geçmez; final SHA üzerinde runner tekrar çalıştırılır ve sonucu yerel summary/kanıt paketinde tutulur.

| Kontrol | Sonuç / sınır |
|---|---|
| Pure normalizer/adaptör | PASS 57; opaque CSRF, row-hook öncesi ret, fallback ve profil sınırı dahil |
| Native kütüphane / kaynak / extraction | PASS 9 + 5 + 32; DB suite'inden ayrı |
| Sentetik Chromium / policy-runner | PASS 98 + 15; gerçek wizard viewport kabulü sayılmaz |
| Gerçek HTTP wizard | PASS geliştirme 101; CSV/XLSX, insert, strict_line |
| Native finansal/iş kayıt snapshot'ı ve temizlik | PASS; boş payment/lot/shipment sınırlamaları korunur |
| Diğer altı import profili / update-key / fast_bulk | NOT RUN |
| Ortak 63, Compose validation, smoke | NOT RUN; Docker/WSL yok |
| Sınıflandırılmamış/third-party, Core kapalı giriş | BLOCKED |
| Eski CI seçim unit'i | Bilinen NTFS filename ERROR; suite PASS ilan edilmez |

[Windows çalışma zamanı hazırlığı](INPUT_NATIVE_WINDOWS.md) ve önceki ücretsiz araçlar kullanılır. Runner yeni araç indirmez; Windows servisi kurmaz. PowerShell, repo kökü:

```powershell
$env:PYTHONUTF8 = '1'
$env:PYTHONPATH = "$PWD/.local/python-packages"
$env:PLAYWRIGHT_BROWSERS_PATH = "$PWD/.local/browsers"
$pythonExe = "$env:USERPROFILE/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe"
& $pythonExe scripts/input_native_local.py --php (Get-Command php).Source --php-ini .local/php.ini --dolibarr .local/upstream/htdocs --mariadb .local/mariadb/mariadb-11.4.13-winx64/bin
& $pythonExe -m unittest discover -s tests/input -v
```

Final yerel commit sonrasında temiz worktree ile aynı komut tekrar çalıştırılır. Exact SHA ve durum `.local/input-native-summary.json`; run dizininde `wizard-checks.json`, `wizard-source-hashes.json` ve mevcut `before.json`/`after.json` dosyalarındadır. Paylaşılabilir pakete yalnız assertion/sayısal/hash raporları alınır; ham source, HTML, config, token, session ve log alınmaz. Temizlik aynı runner'ın sahiplik/PID/çözümlenmiş yol kontrolleriyle yapılır; native upstream hash kümesi geri doğrulanır.

## Açık kapılar

- Bu kabul yalnız **produit_1**, CSV/XLSX, strict_line, insert ve native unique rollback akışıdır. Diğer altı profil, update-key/fast_bulk, özel extrafields/third-party importer ve bütün format/encoding kombinasyonları NOT RUN/BLOCKED durumlarını korur.
- Wizard telefon geometri/erişilebilirlik suite'i ve gerçek CKEditor NOT RUN; önceki dokuz native viewport bu wizard için kullanılamaz.
- Core kapalıyken bu yeni guard/normalizasyon sağlanmaz: BLOCKED. Kapatma, ürün uyumu istisnası değildir.
- Ortak 63, Compose validation ve smoke NOT RUN: Docker/WSL yoktur. Windows loopback runtime, Docker private-network/identity sözleşmesiyle eşdeğer sayılmaz.
- Değişmemiş CI seçim unit suite'inde NTFS satır-sonlu filename fixture hatası devam eder; skip/tolerans eklenmez. Bu Windows sonucu 8 Kasım'da yetkilendirilen hosted runner'daki exact-head PR sonucunun yerine geçmez. Merge/deployment yapılmaz.

Rollback: yayınlanmamış bu devam commit'ini yerelde geri almak, önceki import davranışını (step-5 koruma açığı ve eksik batch hata bildirimi dahil) geri getirir; uyumlu dağıtım olarak sunulmaz. Disposable test DB'si zaten silinir; native müşteri verisi dönüştürülmez.

Sonraki somut iş: zorunlu merkezi büyük harf sınıflandırmasını koruyarak `societe_1` / contact import profillerinde native HTTP başarı, rol/CSRF, kaynak bütünlüğü ve post-conversion sınırlarını doğrulamak.
