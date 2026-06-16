<?php
session_start();
include("db.php");

// ── Pull doctors from DB ─────────────────────────────────────
// doctors table: doctor_id, user_id, full_name, specialization,
//                department, phone, email, availability, profile_photo
$doctors    = [];
$doc_result = $conn->query("
    SELECT doctor_id, full_name, specialization, department,
           availability, profile_photo
    FROM   doctors
    ORDER  BY doctor_id ASC
");
if ($doc_result && $doc_result->num_rows > 0) {
    $doctors = $doc_result->fetch_all(MYSQLI_ASSOC);
}

// Specialty → accent colour
$spec_colors = [
    'ophthalmology'            => '#0284c7',
    'cardiology'               => '#dc2626',
    'pediatrics'               => '#7c3aed',
    'surgery'                  => '#059669',
    'emergency'                => '#ea580c',
    'obstetrics'               => '#db2777',
    'gynaecology'              => '#db2777',
    'neurology'                => '#6d28d9',
    'radiology'                => '#0369a1',
    'laboratory'               => '#065f46',
    'pharmacy'                 => '#16a34a',
    'general'                  => '#16a34a',
];

function spec_color(string $spec, array $map): string {
    $key = strtolower(trim($spec));
    foreach ($map as $k => $v) {
        if (str_contains($key, $k)) return $v;
    }
    return '#16a34a';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hargeisa Group Hospital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ══════════════════════ TOKENS ══════════════════════ */
:root {
  --g900: #052e16;
  --g800: #14532d;
  --g700: #15803d;
  --g600: #16a34a;
  --g500: #22c55e;
  --g400: #4ade80;
  --g100: #dcfce7;
  --g50:  #f0fdf4;
  --ivory:#f9fbf7;
  --mist: #e8f5e9;
  --white:#ffffff;
  --ink:  #0d1f0f;
  --ink2: #374b3a;
  --soft: #6b7f6d;
  --lite: #a8bfaa;
  --gold: #fbbf24;
  --nav:  72px;
  --maxw: 1140px;
  --r:    14px;
  --ease: cubic-bezier(.4,0,.2,1);
  --fd:   'Playfair Display', Georgia, serif;
  --fb:   'Plus Jakarta Sans', system-ui, sans-serif;
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--fb);background:var(--white);color:var(--ink);-webkit-font-smoothing:antialiased;overflow-x:hidden;}

/* ══════════════════════ NAV ══════════════════════ */
.nav{
  position:fixed;top:0;left:0;right:0;height:var(--nav);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 52px;
  background:rgba(5,46,22,.93);
  backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(74,222,128,.1);
  z-index:1000;
  transition:background .3s;
}
.nav__logo{display:flex;align-items:center;gap:12px;text-decoration:none;}
.nav__logo-icon{
  width:40px;height:40px;
  background:linear-gradient(135deg,var(--g600),var(--g800));
  border-radius:11px;
  display:flex;align-items:center;justify-content:center;
  font-size:18px;color:#fff;flex-shrink:0;
  box-shadow:0 4px 12px rgba(22,163,74,.4);
}
.nav__logo-text{font-family:var(--fd);font-size:18px;font-weight:600;color:#fff;line-height:1.1;}
.nav__logo-text span{display:block;font-family:var(--fb);font-size:10.5px;font-weight:300;font-style:italic;color:var(--g400);letter-spacing:.04em;}

.nav__links{display:flex;align-items:center;gap:2px;list-style:none;}
.nav__links a{font-size:14px;font-weight:500;color:rgba(255,255,255,.7);text-decoration:none;padding:7px 15px;border-radius:8px;transition:color .2s,background .2s;}
.nav__links a:hover,.nav__links a.active{color:#fff;background:rgba(74,222,128,.12);}

.nav__btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 22px;border-radius:26px;
  font-family:var(--fb);font-size:14px;font-weight:600;
  text-decoration:none;border:none;cursor:pointer;
  background:var(--g600);color:#fff;
  box-shadow:0 4px 16px rgba(22,163,74,.35);
  transition:all .22s var(--ease);
}
.nav__btn:hover{background:var(--g700);transform:translateY(-1px);box-shadow:0 6px 22px rgba(22,163,74,.45);}

.nav__burger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;background:none;border:none;}
.nav__burger span{display:block;width:22px;height:2px;background:rgba(255,255,255,.7);border-radius:2px;transition:all .2s;}
.nav__mobile{display:none;position:fixed;top:var(--nav);left:0;right:0;background:var(--g900);border-bottom:1px solid rgba(74,222,128,.1);padding:16px 24px 24px;z-index:999;}
.nav__mobile.open{display:block;}
.nav__mobile a{display:block;padding:13px 0;color:rgba(255,255,255,.7);text-decoration:none;font-size:15px;font-weight:500;border-bottom:1px solid rgba(255,255,255,.07);transition:color .2s;}
.nav__mobile a:hover{color:#fff;}
.nav__mobile a:last-child{border-bottom:none;}

/* ══════════════════════ HERO ══════════════════════ */
.hero{
  min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;position:relative;overflow:hidden;
  padding:calc(var(--nav) + 80px) 40px 100px;
  background:var(--g900);
}
.hero__bg{
  position:absolute;inset:0;
  background:
    radial-gradient(ellipse 75% 60% at 50% 35%, rgba(22,163,74,.22) 0%, transparent 65%),
    radial-gradient(ellipse 45% 40% at 10% 80%, rgba(74,222,128,.10) 0%, transparent 60%),
    radial-gradient(ellipse 50% 45% at 90% 70%, rgba(5,150,105,.13) 0%, transparent 60%),
    var(--g900);
  z-index:0;
}
/* Rings */
.ring{position:absolute;border-radius:50%;border:1px solid rgba(74,222,128,.1);pointer-events:none;z-index:0;top:50%;left:50%;animation:rp 8s ease-in-out infinite;}
.ring-1{width:440px;height:440px;transform:translate(-50%,-50%);animation-delay:0s;}
.ring-2{width:720px;height:720px;transform:translate(-50%,-50%);animation-delay:2.5s;}
.ring-3{width:1020px;height:1020px;transform:translate(-50%,-50%);animation-delay:5s;}
@keyframes rp{0%,100%{opacity:.4;transform:translate(-50%,-50%) scale(1);}50%{opacity:.08;transform:translate(-50%,-50%) scale(1.05);}}

/* Corner glyphs */
.hero__glyph{position:absolute;color:rgba(74,222,128,.1);font-size:110px;pointer-events:none;z-index:0;user-select:none;}
.hero__glyph--tl{top:50px;left:50px;transform:rotate(-12deg);}
.hero__glyph--br{bottom:50px;right:50px;font-size:80px;transform:rotate(18deg);}

.hero__content{position:relative;z-index:1;max-width:840px;}

.hero__eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  padding:7px 20px;border-radius:30px;
  background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.22);
  font-size:11px;font-weight:600;color:var(--g400);letter-spacing:.12em;text-transform:uppercase;
  margin-bottom:28px;
  animation:fu .6s var(--ease) both;
}
.hero__title{
  font-family:var(--fd);font-size:clamp(50px,7.5vw,96px);font-weight:700;
  line-height:1.04;color:#fff;
  animation:fu .65s var(--ease) .1s both;
}
.hero__title em{font-style:italic;color:var(--g400);display:block;}

/* Botanical divider */
.hero__div{display:flex;align-items:center;justify-content:center;gap:14px;margin:26px auto;animation:fu .65s var(--ease) .18s both;}
.hero__div-line{width:64px;height:1px;background:linear-gradient(90deg,transparent,rgba(74,222,128,.45));}
.hero__div-line:last-child{background:linear-gradient(270deg,transparent,rgba(74,222,128,.45));}
.hero__div-leaf{color:var(--g400);font-size:20px;}

.hero__sub{
  font-size:18px;font-weight:300;color:rgba(255,255,255,.6);
  line-height:1.72;max-width:600px;margin:0 auto 40px;
  animation:fu .65s var(--ease) .25s both;
}
.hero__actions{display:flex;gap:14px;flex-wrap:wrap;justify-content:center;animation:fu .65s var(--ease) .33s both;}

.btn-h{
  display:inline-flex;align-items:center;gap:9px;
  padding:15px 34px;border-radius:36px;
  font-family:var(--fb);font-size:15px;font-weight:600;
  text-decoration:none;border:none;cursor:pointer;
  transition:all .25s var(--ease);
}
.btn-h--green{background:var(--g600);color:#fff;box-shadow:0 6px 28px rgba(22,163,74,.4);}
.btn-h--green:hover{background:var(--g700);transform:translateY(-2px);box-shadow:0 10px 36px rgba(22,163,74,.5);}
.btn-h--ghost{background:transparent;border:1.5px solid rgba(255,255,255,.2);color:rgba(255,255,255,.8);}
.btn-h--ghost:hover{border-color:var(--g400);color:var(--g400);}

/* Stat strip */
.hero__stats{
  display:flex;justify-content:center;
  margin-top:72px;border-top:1px solid rgba(74,222,128,.13);
  animation:fu .65s var(--ease) .42s both;
}
.hero__stat{padding:28px 44px;border-right:1px solid rgba(74,222,128,.1);text-align:center;}
.hero__stat:last-child{border-right:none;}
.hero__stat-n{font-family:var(--fd);font-size:42px;font-weight:700;color:var(--g400);line-height:1;}
.hero__stat-l{font-size:11px;color:rgba(255,255,255,.38);margin-top:5px;text-transform:uppercase;letter-spacing:.06em;}

@keyframes fu{from{opacity:0;transform:translateY(22px);}to{opacity:1;transform:translateY(0);}}

/* ══════════════════════ SECTIONS ══════════════════════ */
section{padding:100px 52px;}

.tag{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--g600);margin-bottom:12px;}
.tag::before{content:'';display:inline-block;width:20px;height:2px;background:var(--g600);border-radius:2px;}

.s-title{font-family:var(--fd);font-size:clamp(30px,4vw,50px);font-weight:700;color:var(--ink);line-height:1.12;margin-bottom:16px;}
.s-sub{font-size:16px;font-weight:300;color:var(--soft);line-height:1.7;max-width:540px;}

/* ══════════════════════ ABOUT ══════════════════════ */
.about{background:var(--ivory);position:relative;overflow:hidden;}
.about::before{content:'';position:absolute;top:-100px;right:-100px;width:480px;height:480px;border-radius:50%;background:radial-gradient(circle,rgba(22,163,74,.05),transparent 70%);pointer-events:none;}

.about__inner{max-width:var(--maxw);margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:72px;align-items:center;}

.about__pillars{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:32px;}
.about__pillar{background:var(--white);border:1px solid rgba(22,163,74,.12);border-radius:var(--r);padding:22px 18px;transition:all .2s var(--ease);}
.about__pillar:hover{border-color:var(--g500);transform:translateY(-3px);box-shadow:0 8px 28px rgba(22,163,74,.1);}
.about__pillar-icon{width:42px;height:42px;background:var(--g100);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--g600);font-size:17px;margin-bottom:12px;}
.about__pillar h4{font-size:15px;font-weight:600;color:var(--ink);margin-bottom:4px;}
.about__pillar p{font-size:12px;color:var(--soft);line-height:1.55;font-weight:300;}

.about__img-wrap{position:relative;}
.about__img{width:100%;height:480px;object-fit:cover;border-radius:20px;display:block;box-shadow:0 24px 64px rgba(5,46,22,.16);}
.about__img-fb{width:100%;height:480px;border-radius:20px;background:linear-gradient(135deg,var(--g800),var(--g900));display:none;flex-direction:column;align-items:center;justify-content:center;color:rgba(255,255,255,.3);gap:12px;font-size:14px;box-shadow:0 24px 64px rgba(5,46,22,.18);}
.about__img-fb i{font-size:64px;}

.about__badge{
  position:absolute;bottom:-20px;right:-20px;
  background:var(--g600);color:#fff;border-radius:16px;
  padding:18px 22px;text-align:center;
  box-shadow:0 8px 32px rgba(22,163,74,.35);line-height:1.35;
}
.about__badge strong{display:block;font-family:var(--fd);font-size:38px;font-weight:700;}
.about__badge span{font-size:12px;font-weight:400;opacity:.85;}

.about__leaf{position:absolute;top:-28px;left:-28px;font-size:88px;color:rgba(22,163,74,.07);pointer-events:none;}

/* ══════════════════════ SERVICES ══════════════════════ */
.services{background:var(--white);}
.services__inner{max-width:var(--maxw);margin:0 auto;}
.services__head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:48px;gap:24px;flex-wrap:wrap;}
.services__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;}

.svc{background:var(--g50);border:1px solid rgba(22,163,74,.12);border-radius:var(--r);padding:30px 22px;transition:all .25s var(--ease);position:relative;overflow:hidden;}
.svc::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--g600),var(--g400));transform:scaleX(0);transform-origin:left;transition:transform .3s var(--ease);}
.svc:hover{border-color:var(--g500);transform:translateY(-5px);box-shadow:0 12px 36px rgba(22,163,74,.12);background:var(--white);}
.svc:hover::after{transform:scaleX(1);}
.svc__icon{width:54px;height:54px;border-radius:14px;background:var(--g100);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--g700);margin-bottom:18px;transition:all .25s;}
.svc:hover .svc__icon{background:var(--g600);color:#fff;}
.svc__name{font-size:16px;font-weight:600;color:var(--ink);margin-bottom:6px;}
.svc__desc{font-size:13px;color:var(--soft);line-height:1.55;font-weight:300;}

/* ══════════════════════ DOCTORS ══════════════════════ */
.doctors{background:var(--mist);}
.doctors__inner{max-width:var(--maxw);margin:0 auto;}
.doctors__head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:48px;gap:24px;flex-wrap:wrap;}
.doctors__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:26px;}

.doctors__empty{text-align:center;padding:64px 24px;border:2px dashed rgba(22,163,74,.2);border-radius:var(--r);color:var(--soft);background:var(--white);}
.doctors__empty i{font-size:48px;color:rgba(22,163,74,.3);margin-bottom:14px;display:block;}

.doc{background:var(--white);border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(5,46,22,.07);transition:all .3s var(--ease);border:1px solid rgba(22,163,74,.07);}
.doc:hover{transform:translateY(-6px);box-shadow:0 20px 52px rgba(5,46,22,.15);border-color:rgba(22,163,74,.22);}

.doc__photo{position:relative;height:280px;overflow:hidden;background:linear-gradient(135deg,var(--g800),var(--g900));}
.doc__photo img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block;transition:transform .4s var(--ease);}
.doc:hover .doc__photo img{transform:scale(1.05);}
.doc__photo-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--g800),var(--g700));}
.doc__photo-ph i{font-size:72px;color:rgba(255,255,255,.2);}
.doc__overlay{position:absolute;inset:0;background:linear-gradient(to bottom,transparent 45%,rgba(5,46,22,.65) 100%);}
.doc__pill{position:absolute;top:14px;left:14px;padding:5px 13px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#fff;backdrop-filter:blur(10px);z-index:2;}

.doc__body{padding:22px 24px 24px;}
.doc__name{font-family:var(--fd);font-size:21px;font-weight:600;color:var(--ink);margin-bottom:4px;line-height:1.2;}
.doc__dept{font-size:13px;color:var(--soft);margin-bottom:12px;}
.doc__sch{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--soft);margin-bottom:20px;}
.doc__sch i{color:var(--g600);font-size:12px;width:14px;}
.doc__btn{
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:13px;border-radius:12px;
  background:var(--g600);color:#fff;
  font-family:var(--fb);font-size:14px;font-weight:600;
  text-decoration:none;border:none;cursor:pointer;
  box-shadow:0 4px 14px rgba(22,163,74,.25);
  transition:all .22s var(--ease);
}
.doc__btn:hover{background:var(--g700);box-shadow:0 6px 22px rgba(22,163,74,.4);transform:translateY(-1px);}

/* ══════════════════════ PROMO ══════════════════════ */
.promo{background:var(--white);padding:0 52px 80px;}
.promo__inner{
  max-width:var(--maxw);margin:0 auto;
  background:linear-gradient(135deg,var(--g800) 0%,var(--g900) 65%,#071a0e 100%);
  border:1px solid rgba(74,222,128,.13);border-radius:24px;
  padding:56px 64px;display:flex;align-items:center;justify-content:space-between;gap:40px;flex-wrap:wrap;
  position:relative;overflow:hidden;
}
.promo__inner::before{content:'';position:absolute;right:-80px;top:-80px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(74,222,128,.07),transparent 70%);pointer-events:none;}
.promo__label{display:inline-flex;align-items:center;gap:7px;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.25);color:var(--gold);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;padding:5px 12px;border-radius:20px;margin-bottom:14px;}
.promo__title{font-family:var(--fd);font-size:clamp(26px,3vw,42px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:10px;}
.promo__sub{font-size:15px;color:rgba(255,255,255,.5);font-weight:300;max-width:440px;}
.promo__badge{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:var(--g900);border-radius:18px;padding:24px 34px;text-align:center;flex-shrink:0;box-shadow:0 8px 32px rgba(251,191,36,.25);}
.promo__badge-n{font-family:var(--fd);font-size:60px;font-weight:700;line-height:1;display:block;}
.promo__badge-l{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-top:4px;display:block;}

/* ══════════════════════ TESTIMONIALS ══════════════════════ */
.testi{background:var(--g50);}
.testi__inner{max-width:var(--maxw);margin:0 auto;}
.testi__grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:48px;}

.tc{background:var(--white);border:1px solid rgba(22,163,74,.1);border-radius:18px;padding:30px;transition:all .25s var(--ease);}
.tc:hover{border-color:rgba(22,163,74,.28);box-shadow:0 8px 32px rgba(22,163,74,.08);transform:translateY(-3px);}
.tc__q{font-size:48px;font-family:var(--fd);color:var(--g500);opacity:.3;line-height:1;margin-bottom:10px;}
.stars{color:#fbbf24;font-size:13px;margin-bottom:14px;}
.tc__txt{font-size:15px;color:var(--ink2);line-height:1.7;font-style:italic;font-weight:300;margin-bottom:22px;}
.tc__auth{display:flex;align-items:center;gap:12px;}
.tc__av{width:42px;height:42px;border-radius:50%;background:var(--g100);display:flex;align-items:center;justify-content:center;color:var(--g600);font-size:16px;flex-shrink:0;}
.tc__name{font-size:14px;font-weight:600;color:var(--ink);}
.tc__role{font-size:12px;color:var(--lite);margin-top:2px;}

/* ══════════════════════ FOOTER ══════════════════════ */
footer{background:var(--g900);border-top:1px solid rgba(74,222,128,.1);padding:72px 52px 40px;}
.footer__inner{max-width:var(--maxw);margin:0 auto;}
.footer__top{display:grid;grid-template-columns:2.2fr 1fr 1fr 1fr;gap:56px;margin-bottom:60px;}
.footer__bn{font-family:var(--fd);font-size:22px;font-weight:600;color:#fff;margin-bottom:12px;}
.footer__bd{font-size:14px;color:rgba(255,255,255,.38);line-height:1.7;font-weight:300;margin-bottom:22px;}
.footer__soc{display:flex;gap:10px;}
.footer__soc a{width:36px;height:36px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.38);font-size:14px;text-decoration:none;transition:all .2s;}
.footer__soc a:hover{border-color:var(--g400);color:var(--g400);background:rgba(74,222,128,.08);}
.footer__ct{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.28);margin-bottom:18px;}
.footer__col ul{list-style:none;}
.footer__col li{margin-bottom:10px;}
.footer__col a{font-size:14px;color:rgba(255,255,255,.42);text-decoration:none;font-weight:300;transition:color .2s;}
.footer__col a:hover{color:var(--g400);}
.footer__ci{display:flex;align-items:flex-start;gap:9px;font-size:14px;color:rgba(255,255,255,.38);font-weight:300;margin-bottom:11px;line-height:1.55;}
.footer__ci i{color:var(--g400);font-size:13px;margin-top:2px;flex-shrink:0;}
.footer__bot{padding-top:28px;border-top:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.footer__copy{font-size:13px;color:rgba(255,255,255,.22);font-weight:300;}

/* ══════════════════════ REVEAL ══════════════════════ */
.reveal{opacity:0;transform:translateY(26px);transition:opacity .55s var(--ease),transform .55s var(--ease);}
.reveal.visible{opacity:1;transform:none;}
.rc{opacity:0;transform:translateY(18px);transition:opacity .45s var(--ease),transform .45s var(--ease);}
.rc.visible{opacity:1;transform:none;}

/* ══════════════════════ RESPONSIVE ══════════════════════ */
@media(max-width:960px){
  .about__inner{grid-template-columns:1fr;}
  .about__img-wrap{order:-1;}
  .footer__top{grid-template-columns:1fr 1fr;gap:36px;}
  .testi__grid{grid-template-columns:1fr;}
  .nav__links,.nav__right-d{display:none;}
  .nav__btn-desk{display:none;}
  .nav__burger{display:flex;}
  section,.promo{padding-left:24px;padding-right:24px;}
  footer{padding-left:24px;padding-right:24px;}
}
@media(max-width:640px){
  .nav{padding:0 20px;}
  .hero__stats{flex-direction:column;}
  .hero__stat{border-right:none;border-top:1px solid rgba(74,222,128,.1);padding:18px 0;}
  .promo__inner{padding:32px 26px;}
  .footer__top{grid-template-columns:1fr;}
  .doctors__grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ══ NAV ══════════════════════════════════════════════ -->
<header class="nav" id="nav">
  <a href="#" class="nav__logo">
    <div class="nav__logo-icon"><i class="fa fa-hospital-user"></i></div>
    <div class="nav__logo-text">
      Hargeisa Group Hospital
      <span>Est. 1953 · Somaliland</span>
    </div>
  </a>

  <nav>
    <ul class="nav__links">
      <li><a href="#"         class="active">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#services">Services</a></li>
      <li><a href="#doctors">Doctors</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>
  </nav>

  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="register.php" class="nav__btn nav__btn-desk">
      <i class="fa fa-calendar-plus"></i> Book Appointment
    </a>
  <?php else: ?>
    <a href="login.php" class="nav__btn nav__btn-desk">
      <i class="fa fa-right-to-bracket"></i> Sign In
    </a>
  <?php endif; ?>

  <button class="nav__burger" id="burger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</header>

<div class="nav__mobile" id="mobileNav">
  <a href="#">Home</a>
  <a href="#about">About</a>
  <a href="#services">Services</a>
  <a href="#doctors">Doctors</a>
  <a href="#contact">Contact</a>
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="register.php" style="color:var(--g400);">Book Appointment</a>
  <?php else: ?>
    <a href="login.php" style="color:var(--g400);">Sign In</a>
  <?php endif; ?>
</div>


<!-- ══ HERO ══════════════════════════════════════════════ -->
<section class="hero" id="home">
  <div class="hero__bg"></div>
  <div class="ring ring-1"></div>
  <div class="ring ring-2"></div>
  <div class="ring ring-3"></div>
  <div class="hero__glyph hero__glyph--tl">✦</div>
  <div class="hero__glyph hero__glyph--br">✦</div>

  <div class="hero__content">
    <p class="hero__eyebrow">
      <i class="fa fa-circle-check"></i>
      Somaliland's Premier Healthcare Provider
    </p>

    <h1 class="hero__title">
      Your Health Is
      <em>Our Priority</em>
    </h1>

    <div class="hero__div">
      <span class="hero__div-line"></span>
      <span class="hero__div-leaf">🌿</span>
      <span class="hero__div-line"></span>
    </div>

    <p class="hero__sub">
      Where compassion meets care. Hargeisa Group Hospital has served
      the people of Somaliland since 1953 — with expert doctors,
      modern facilities, and a patient-first approach.
    </p>

    <div class="hero__actions">
      <a href="login.php" class="btn-h btn-h--green">
        <i class="fa fa-calendar-plus"></i> Book an Appointment
      </a>
      <a href="#about" class="btn-h btn-h--ghost">
        Learn More <i class="fa fa-arrow-down"></i>
      </a>
    </div>

    <div class="hero__stats">
      <div class="hero__stat">
        <div class="hero__stat-n" data-target="400">400+</div>
        <div class="hero__stat-l">Hospital Beds</div>
      </div>
      <div class="hero__stat">
        <div class="hero__stat-n"><?= count($doctors) > 0 ? count($doctors) : '70' ?>+</div>
        <div class="hero__stat-l">Specialist Doctors</div>
      </div>
      <div class="hero__stat">
        <div class="hero__stat-n">1953</div>
        <div class="hero__stat-l">Established</div>
      </div>
      <div class="hero__stat">
        <div class="hero__stat-n">24/7</div>
        <div class="hero__stat-l">Emergency Care</div>
      </div>
    </div>
  </div>
</section>


<!-- ══ ABOUT ══════════════════════════════════════════════ -->
<section id="about" class="about reveal">
  <div class="about__inner">

    <div>
      <p class="tag">About Us</p>
      <h2 class="s-title">Serving Somaliland<br>For Over 70 Years</h2>
      <p class="s-sub">
        Established in 1953, the Hargeisa Group Hospital is the largest public
        hospital in Somaliland — a 400-bed facility providing compassionate,
        expert healthcare to hundreds of thousands of patients every year.
      </p>

      <div class="about__pillars">
        <div class="about__pillar rc">
          <div class="about__pillar-icon"><i class="fa fa-award"></i></div>
          <h4>Quality Care</h4>
          <p>Accredited standards &amp; evidence-based medical practice</p>
        </div>
        <div class="about__pillar rc">
          <div class="about__pillar-icon"><i class="fa fa-user-doctor"></i></div>
          <h4>Expert Doctors</h4>
          <p>Specialists across all major medical disciplines</p>
        </div>
        <div class="about__pillar rc">
          <div class="about__pillar-icon"><i class="fa fa-hospital"></i></div>
          <h4>Modern Facilities</h4>
          <p>Up-to-date equipment &amp; advanced diagnostic tools</p>
        </div>
        <div class="about__pillar rc">
          <div class="about__pillar-icon"><i class="fa fa-heart"></i></div>
          <h4>Patient First</h4>
          <p>Every patient treated with dignity, care &amp; respect</p>
        </div>
      </div>
    </div>

    <!-- Real image from images/about_us.jpg -->
    <div class="about__img-wrap">
        
      <span class="about__leaf">🌿</span>
      <img
       src="images/about us.png" alt="About Hospital" 
     style="width:100%; height:100%; object-fit:cover; border-radius:20px;">
     
      <div class="about__img-fb" id="about-fb">
        <i class="fa fa-hospital"></i>
        <p>Hargeisa Group Hospital</p>
        <small style="font-size:11px;opacity:.5;margin-top:4px;">Add your image at: images/about_us.jpg</small>
      </div>
      <div class="about__badge">
        <strong>70+</strong>
        <span>Years of Dedicated<br>Healthcare</span>
      </div>
    </div>

  </div>
</section>


<!-- ══ SERVICES ══════════════════════════════════════════ -->
<section id="services" class="services reveal">
  <div class="services__inner">
    <div class="services__head">
      <div>
        <p class="tag">What We Offer</p>
        <h2 class="s-title">Our Medical Services</h2>
      </div>
      <p class="s-sub">Comprehensive healthcare delivered across all major specialties by trusted professionals.</p>
    </div>
    <div class="services__grid">
      <?php
      $svcs = [
        ['fa-heart-pulse',   'Cardiology',   'Heart disease diagnosis, ECG &amp; cardiac monitoring'],
        ['fa-baby',          'Pediatrics',   'Child health, immunization &amp; development care'],
        ['fa-scalpel',       'Surgery',      'General &amp; minimally invasive surgical procedures'],
        ['fa-truck-medical', 'Emergency',    '24/7 trauma, critical &amp; emergency response'],
        ['fa-flask',         'Laboratory',   'Full diagnostic lab testing &amp; pathology services'],
        ['fa-pills',         'Pharmacy',     'In-house pharmacy with a wide range of medications'],
        ['fa-eye',           'Ophthalmology','Eye exams, vision care &amp; surgical procedures'],
        ['fa-brain',         'Neurology',    'Brain, spine &amp; neurological disorder treatment'],
        ['fa-x-ray',         'Radiology',    'X-ray, ultrasound &amp; advanced imaging services'],
      ];
      foreach ($svcs as [$ico, $nm, $desc]): ?>
        <div class="svc rc">
          <div class="svc__icon"><i class="fa <?= $ico ?>"></i></div>
          <div class="svc__name"><?= $nm ?></div>
          <div class="svc__desc"><?= $desc ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="doctors" class="doctors reveal">
  <div class="doctors__inner">
    <div class="doctors__head">
      <div>
        <p class="tag">Our Team</p>
        <h2 class="s-title">Meet Our Specialists</h2>
      </div>
      <p class="s-sub">A dedicated team of experienced doctors committed to your health and well-being.</p>
    </div>

    <?php if (empty($doctors)): ?>
      <div class="doctors__empty">
        <i class="fa fa-user-doctor"></i>
        <p style="font-size:16px;font-weight:600;margin-bottom:6px;">No doctors listed yet</p>
        <p style="font-size:14px;">Doctor profiles will appear here once added to the system.</p>
      </div>
    <?php else: ?>
      <div class="doctors__grid">
        <?php foreach ($doctors as $doc):
          $color    = spec_color($doc['specialization'] ?? '', $spec_colors);
          
          // CORRECTED: Removed the 'uploads/doctors/' string because it is already in the DB
          $photo    = !empty($doc['profile_photo']) 
                      ? htmlspecialchars($doc['profile_photo']) 
                      : '';
                      
          $enc_name = urlencode($doc['full_name'] ?? '');
          $doc_id   = (int)($doc['doctor_id'] ?? 0);
        ?>
        <div class="doc rc">
          <div class="doc__photo">
            <?php if ($photo): ?>
              <img 
                src="<?= $photo ?>" 
                alt="<?= htmlspecialchars($doc['full_name'] ?? 'Doctor') ?>" 
                loading="lazy"
                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
              >
              <div class="doc__photo-ph" style="display:none;"><i class="fa fa-user-doctor"></i></div>
            <?php else: ?>
              <div class="doc__photo-ph"><i class="fa fa-user-doctor"></i></div>
            <?php endif; ?>
            <div class="doc__overlay"></div>
            <span class="doc__pill" style="background:<?= $color ?>cc;">
              <?= htmlspecialchars($doc['specialization'] ?? 'Specialist') ?>
            </span>
          </div>

          <div class="doc__body">
            <p class="doc__name"><?= htmlspecialchars($doc['full_name'] ?? 'Doctor') ?></p>
            <?php if (!empty($doc['department'])): ?>
              <p class="doc__dept"><?= htmlspecialchars($doc['department']) ?></p>
            <?php endif; ?>
            <p class="doc__sch">
              <i class="fa fa-clock"></i> 
              <?= htmlspecialchars($doc['availability'] ?? 'By appointment') ?>
            </p>
            <a href="login.php?doctor_id=<?= $doc_id ?>&doctor=<?= $enc_name ?>" 
               class="doc__btn">
              <i class="fa fa-calendar-plus"></i> Book Appointment
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ══ PROMO ══════════════════════════════════════════════ -->
<div class="promo reveal">
  <div class="promo__inner">
    <div>
      <p class="promo__label"><i class="fa fa-star"></i> Limited Time Offer</p>
      <h2 class="promo__title">Free Health Screening<br>This Month</h2>
      <p class="promo__sub">
        Book a general health screening and receive a comprehensive check-up at a reduced rate.
        Prioritise your health today.
      </p>
    </div>
    <div class="promo__badge">
      <span class="promo__badge-n">20%</span>
      <span class="promo__badge-l">Off Screenings</span>
    </div>
  </div>
</div>


<!-- ══ TESTIMONIALS ══════════════════════════════════════ -->
<section class="testi reveal">
  <div class="testi__inner">
    <p class="tag">Patient Reviews</p>
    <h2 class="s-title">What Our Patients Say</h2>
    <div class="testi__grid">
      <?php
      $reviews = [
        ['Excellent service and incredibly friendly doctors. I felt truly cared for from the moment I arrived. The staff went above and beyond.',   'Ahmed Hassan',  'Patient · Cardiology'],
        ['Modern system, fast treatment, and the booking process was effortless. A world-class experience right here in Hargeisa.',                 'Fadumo Ismail', 'Patient · Pediatrics'],
        ['The ophthalmology team was exceptional — professional, thorough, and reassuring. I cannot recommend this hospital enough.',               'Mohamed Abdi',  'Patient · Ophthalmology'],
        ['Clean facilities, very short wait times, and doctors who genuinely listen. I am grateful for the excellent care I received.',             'Hodan Warsame', 'Patient · Surgery'],
      ];
      foreach ($reviews as [$txt, $name, $role]): ?>
        <div class="tc rc">
          <div class="tc__q">"</div>
          <div class="stars">★★★★★</div>
          <p class="tc__txt"><?= htmlspecialchars($txt) ?></p>
          <div class="tc__auth">
            <div class="tc__av"><i class="fa fa-user"></i></div>
            <div>
              <div class="tc__name"><?= htmlspecialchars($name) ?></div>
              <div class="tc__role"><?= htmlspecialchars($role) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ══ FOOTER ════════════════════════════════════════════ -->
<footer id="contact">
  <div class="footer__inner">
    <div class="footer__top">
      <div>
        <div class="footer__bn">Hargeisa Group Hospital</div>
        <p class="footer__bd">Somaliland's largest public hospital — serving our community with compassionate, quality healthcare since 1953.</p>
        <div class="footer__soc">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      <div class="footer__col">
        <p class="footer__ct">Navigation</p>
        <ul>
          <li><a href="#">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#doctors">Doctors</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer__col">
        <p class="footer__ct">Services</p>
        <ul>
          <li><a href="#services">Cardiology</a></li>
          <li><a href="#services">Pediatrics</a></li>
          <li><a href="#services">Surgery</a></li>
          <li><a href="#services">Emergency</a></li>
          <li><a href="#services">Laboratory</a></li>
        </ul>
      </div>
      <div class="footer__col">
        <p class="footer__ct">Contact</p>
        <div class="footer__ci"><i class="fa fa-location-dot"></i><span>26 June Hospital Road,<br>Hargeisa, Somaliland</span></div>
        <div class="footer__ci"><i class="fa fa-phone"></i><span>+252 2 520 450</span></div>
        <div class="footer__ci"><i class="fa fa-envelope"></i><span>info@hgh.so</span></div>
        <div class="footer__ci"><i class="fa fa-clock"></i><span>Emergency: 24/7<br>Outpatient: 8:00 – 17:00</span></div>
      </div>
    </div>
    <div class="footer__bot">
      <p class="footer__copy">© <?= date('Y') ?> Hargeisa Group Hospital. All rights reserved.</p>
      <p class="footer__copy">Built with care for the people of Somaliland 🌿</p>
    </div>
  </div>
</footer>

<script>
/* Mobile nav */
const burger = document.getElementById('burger');
const mob    = document.getElementById('mobileNav');
burger.addEventListener('click', () => mob.classList.toggle('open'));
mob.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mob.classList.remove('open')));

/* Sticky nav */
const navEl = document.getElementById('nav');
window.addEventListener('scroll', () => {
  navEl.style.background = window.scrollY > 50 ? 'rgba(5,46,22,.99)' : 'rgba(5,46,22,.93)';
}, { passive:true });

/* Active link */
const secs = document.querySelectorAll('section[id],footer[id]');
const nls  = document.querySelectorAll('.nav__links a');
new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting)
      nls.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
  });
}, { threshold:.35 }).observe && secs.forEach(s => {
  new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting)
        nls.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
    });
  }, { threshold:.35 }).observe(s);
});

/* Scroll reveal */
const ro = new IntersectionObserver((entries, obs) => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) {
      e.target.style.transitionDelay = (i * 0.06) + 's';
      e.target.classList.add('visible');
      obs.unobserve(e.target);
    }
  });
}, { threshold:.08 });

document.querySelectorAll('.reveal,.rc,.doc,.svc,.about__pillar,.tc').forEach(el => ro.observe(el));
</script>
</body>
</html>