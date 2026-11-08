"""Closed acceptance manifest: complete Cartesian viewport sets, no tolerance."""
import json
from pathlib import Path
import shutil

WIDTHS=(360,390,768)
SURFACES={'native':('societe/list.php','product/list.php','commande/list.php','expedition/list.php','compta/facture/list.php'),
          'core':('home','settings','administration'),'orders':('inbox','packet','review'),
          'customs':('list','detail','create','edit','match','status'),'mail':('accounts','inbox','message','settings')}

def strict(rows,lane):
    normalized=[]
    for r in rows:
        if lane=='native':
            if r['phase']!='after':continue
            width=r['width'];screen=r['surface'];body=r['body'];doc=r['document'];viewport=r['width']
        elif lane=='core':width=r['width'];screen=r['screen'];body=r['body_scroll_width'];doc=r['document_scroll_width'];viewport=r['viewport']
        elif lane=='orders':width=r['width'];screen=r['screen'];body=r['body'];doc=r['document'];viewport=r['viewport']
        elif lane=='customs':width=r['width'];screen=r['screen'];body=r['body'];doc=r['document'];viewport=r['width']
        else:width=r['viewport'];screen=r['screen'];body=r['body'];doc=r['doc'];viewport=r['viewport']
        assert viewport==width and max(body,doc)<=width,'STRICT document overflow in '+lane
        normalized.append({'surface':screen,'width':width,'body':body,'document':doc})
    expected={(w,s) for w in WIDTHS for s in SURFACES[lane]}
    assert len(normalized)==len(expected) and {(r['width'],r['surface']) for r in normalized}==expected,'Missing or duplicate viewport evidence in '+lane
    return normalized

def collect():
    out=Path('.local/coenabled-evidence'); results={}
    paths={'native':out/'native-layout.json','core':Path('.local/maxcore-mobile.json'),'orders':Path('.local/orders-mobile.json'),'customs':Path('.local/customs-layout.json'),'mail':Path('.local/mailintake-layout.json')}
    for lane,path in paths.items(): results[lane]=strict(json.loads(path.read_text()),lane)
    for lane in ('core','orders','customs','mail'): assert json.loads((out/(lane+'-observer.json')).read_text())['passed'],'Failed browser observer'
    assert json.loads((out/'native-browser.json').read_text())['passed'],'Native hook failure'
    final=json.loads((out/'final.json').read_text());assert final['baseline_unchanged'] and final['no_send'] and len(final['modules'])==5
    assert final['native_before']==final['native_after'] and final['mail_modules_before']==final['mail_modules_after']
    assert final['installation_fingerprint']==json.loads((out/'identity.json').read_text())['installation_fingerprint']
    # Only public Core/Orders and source-redacted Customs screenshots; Mail is numeric only.
    patterns=['maxcore*-360.png','maxcore*-390.png','maxcore*-768.png','orders-*.png','customs-view-*.png']
    for pattern in patterns:
        for file in Path('.local').glob(pattern):shutil.copyfile(file,out/file.name)
    for lane,rows in results.items():
        (out/(lane+'-strict.json')).write_text(json.dumps(rows,indent=2))
    order_writes=json.loads((out/'order-write-checks.json').read_text())
    assert order_writes and all(row.get('status')=='PASS' for row in order_writes),'Native order-write HTTP assertion failed or incomplete'
    assert order_writes[-1].get('check')=='order write final owned draft cleanup and network checks','Missing native order-write terminal assertion'
    report={'passed':True,'source':json.loads((out/'source.json').read_text()),'shared_db':json.loads((out/'identity.json').read_text()),'counts':{**{lane:len(rows) for lane,rows in results.items()},'native_order_write_http':len(order_writes)},'strict_total':sum(map(len,results.values())),'manual_gates':['physical-device','WSL2-workstation','provider-TLS-OAuth','PDF-extraction-and-release','HKS','commercial-distribution-a11y-review']}
    assert report['strict_total']==63
    (out/'acceptance.json').write_text(json.dumps(report,indent=2));print('PASS all 63 exact per surface STRICT viewport checks with shared DB evidence')
if __name__=='__main__':collect()
