# Cari/kişi native HTTP import kabulü — Issue #36

Başlangıç yerel commit'i `089b2abf1a9760f763924bb2ac6b7f396038fd57`; aynı `feat/max-uppercase-inputs` dalı. Sahibinin sonraki işi uygulama ve proje durum raporu talimatı kapsamında yalnız ücretsiz, disposable, sentetik yerel kaynaklar kullanılır. GitHub/CI/push/PR, ücretli servis, merge ve deployment askısı devam eder.

## Alan sınıflandırması ve düzeltmeler

- `societe_1`: native müşteri/tedarikçi adı, alias, adres, şehir ve private/public notlar business text/rich text. Müşteri/tedarikçi kodu, e-posta, URL ve typed durumlar teknik; raw CSV/XLSX immutable source.
- `societe_2`: lastname/firstname, adres, şehir, görev ve notlar business text/rich text. Native rowid, şirket bağlantısının `id:` referansı, e-posta ve mapping teknik/opaque. Var olan korunan müşteriye native foreign key ile bağlanılır; yeni ERP gölge kaydı yoktur.
- Native `llx_socpeople.sql`, ad ve soyadı **varchar(50)** tutuyor. Registry'deki 100 sınırı web `contact/card.php`, native Contacts API ve kişi importu için 50'ye düzeltildi. `ß × 25 → SS × 25` tam 50 karakter kabul edilir; 26 adet ß'nin 52 karaktere genişlemesi kesilmeden reddedilir. SQL kolonları değiştirilmez. Görev için UI metadata ile SQL farklı olsa da gerçek native SQL sınırı 255'tir; mevcut import kuralı korunur. Şehirdeki mevcut daha dar 64 sınırı bu görevde genişletilmez.
- Kişi importundaki `s.note_public` ve `s.note_private`, merkezi registry'ye `html,65535` olarak eklendi. Rich normalizer kullanılır; native sanitizing/WAF devam eder. Gerçek HTTP senaryosu plain prose notlarını sınar; bütün rich HTML/editor varyantlarını kanıtlamaz.

Mevcut [ürün wizard testi](../tests/input/wizard_http.py) yeniden yazılmaz. CSV/XLSX fixture üreticisi header/kolon sayısını parametre olarak kabul edecek şekilde genişletilir ve yeni [people_http.py](../tests/input/people_http.py) tarafından kullanılır. Native yükleme, public mapping isteği, simülasyon formu ve gerçek Run Import linki aynen kullanılır; SQL veya session mapping seed'i yoktur.

## Test edilen native akışlar

Her iki profil × CSV/XLSX için:

1. Gerçek upload/mapping/simülasyon formları ve exact upload baytları.
2. Türkçe dotted/dotless I, decomposed NFC ve Unicode genişlemesinden sonra kaydedilen değerler; e-posta/URI ve kodların exact korunması.
3. `societe_1` kaydının aynı anda müşteri ve tedarikçi olması; native code_client/code_fournisseur doğrulaması.
4. `societe_2` ad/soyad/görev/adres/not dönüşümü, typed rowid ve korunan native şirketle bağlantı; ek satırda iki ad alanının tam 50 karakter sınırı.
5. Simülasyonun DB'ye kayıt bırakmaması; token'sız/yanlış token GET/POST'un 403 alıp yazmaması.
6. Native Run Import linkinin mevcut Core token/POST adaptörüyle gerçek commit'i; bağımsız native Thirdparties/Contacts API üzerinden persistence okuması.
7. Duplicate batch'te önceki pending satırın da rollback olması; var olan kaydın aynen kalması ve batch rejection mesajı. Şirkette unique müşteri kodu, kişide native primary key kullanılır; ad/soyad unique gibi gösterilmez.
8. Şirket adında 128, kişi ad/soyadında 50 sınırının dönüşüm sonrası aşılması; hem simülasyon hem doğrudan gerçek importta kesme/merge olmadan ret.
9. Geçerli token'ı olan import.run yetkisiz native kullanıcının upload/import reddi; yanlış upload token'ında dosya oluşmaması.
10. Kişi API'sine doğrudan 52 karaktere genişleyen firstname update'i: 400 ve önceki kaydın değişmemesi.
11. 18 raw upload hash'inin son kontrolde aynı olması; JS hatası ve dış browser isteği olmaması.

Native fixture'a bir korunan Contact eklendi; ticari before/after snapshot artık bu sabit kişi satırını da içerir. Snapshot bütün fixture'lardan sonra bir kez alınır, wizard/import/lifecycle sonrasında aynı sorgu/küme ile karşılaştırılır. İzinli yeni test müşteri/kişileri bu sabit kimlik kümesi dışındadır; önceden dolu banka/IBAN/stok/sipariş/draft fatura, legal sabitler, korunan müşteri/kişi ve diğer entity ürünü değişemez. Payment/lot/shipment tablolarının boş olduğu eski dar fixture sınırı korunur.

## Kanıt ve yeniden üretim

Geliştirme koşusunda **247 people HTTP assertion**, önceki **38 fixture + 50 form/API + 101 ürün wizard + 29 importer/lifecycle/snapshot** assertion ile PASS. Sonrasında iki contact API overflow regresyonu eklendi; final sayı exact commit koşusunun `people-checks.json` dosyasından okunur. Dirty-tree development sonucu teslim SHA'sına mal edilmez.

```powershell
$env:PYTHONUTF8 = '1'
$env:PYTHONPATH = "$PWD/.local/python-packages"
$env:PLAYWRIGHT_BROWSERS_PATH = "$PWD/.local/browsers"
$pythonExe = "$env:USERPROFILE/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe"
& $pythonExe scripts/input_native_local.py --php (Get-Command php).Source --php-ini .local/php.ini --dolibarr .local/upstream/htdocs --mariadb .local/mariadb/mariadb-11.4.13-winx64/bin
& $pythonExe -m unittest discover -s tests/input -v
```

Runtime ve temizlik sözleşmesi [INPUT_NATIVE_WINDOWS.md](INPUT_NATIVE_WINDOWS.md) içindedir. Yeni aşama `.local/input-native-summary.json` içinde `native_people_http` olarak ayrı raporlanır; kendi terminal assertion'ı olmadan PASS üretemez. Run dizininde `people-checks.json` ve `people-source-hashes.json` bulunur. Native kaynaklar hash ile geri doğrulanır; owned süreç/DB/documents/session/credentials kaldırılır. Yalnız sentetik assertion/hash/sayısal raporlar paylaşılır; raw dosya, HTML, config, token ve log paylaşım paketine alınmaz.

## Kapsam ve kalan iş

| Yüzey | Durum |
|---|---|
| Ürün/cari/kişi: 3/7 profil, CSV ve XLSX insert/strict_line | Bu runner'da native HTTP kabulü; final SHA raporu ayrı |
| Diğer 4/7 profil, update-key, fast_bulk | NOT RUN |
| Kişi web ad/soyad sınırı | Merkezi guard unit + native SQL sınır eşlemesi; tüm native card etkileşimleri NOT RUN |
| Diğer native web/API, gerçek CKEditor, wizard mobil geometri | Kapsamı tamamlanmamış / NOT RUN |
| ORM/cron/third-party ve Core kapalı girişler | BLOCKED |
| Ortak 63, Compose validation, smoke | NOT RUN; Docker/WSL yok |
| CI seçim unit'inin satır-sonlu filename fixture'ı | NTFS OSError/Errno 22; skip/tolerans eklenmez |

Rollback: yayınlanmamış bu devam commit'ini yerelde geri almak, eski fazla geniş kişi adı limitini ve eksik kişi notu sınıflandırmasını geri getirir; uyumlu dağıtım kabulü değildir. Disposable fixture verisi silinir; mevcut müşteri verisi/migration/core değişikliği yoktur.

Genel proje durum ve kalan kabul yüzdesi: [PROJECT_STATUS.md](PROJECT_STATUS.md). Bu yeni zorunlu normalizasyon kapısı eski 25 senaryo matrisine eklenmiş bir ID değildir; profillerdeki ilerleme ayrı izlenir. Sonraki somut iş, aynı MaxInput sözleşmesiyle native sipariş/satır import profillerini doğrulamaktır.
