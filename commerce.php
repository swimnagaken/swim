<?php
// 設定ファイルの読み込み
require_once 'config/config.php';

// ページタイトル
$page_title = "特定商取引法に基づく表記";

// ヘッダーの読み込み
include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/legal.css">

<div class="legal-container">
    <div class="legal-header">
        <h1>📋 特定商取引法に基づく表記</h1>
    </div>
    
    <div class="navigation-links">
        <a href="terms.php">利用規約</a> |
        <a href="privacy.php">プライバシーポリシー</a>
    </div>
    
    <div class="legal-content">
        <p class="intro-text">本サービスは基本的に無料でご利用いただけますが、将来的に有料サービスを提供する可能性があるため、特定商取引法に基づく表記を行います。</p>
        
        <h3>事業者情報</h3>
        <ul class="info-list">
            <li><strong>屋号：</strong>Cre.eight12（クリエイトじゅうに）</li>
            <li><strong>代表者氏名：</strong>永江健太郎</li>
            <li><strong>所在地：</strong>新潟県長岡市[詳細な所在地につきましては、メールにてご請求いただければ、原則3営業日以内に開示いたします。]</li>
            <li><strong>メールアドレス：</strong>cre.eight12@gmail.com</li>
            <li><strong>電話番号：</strong>メールにてご請求いただければ、原則1週間以内に開示いたします。 </li>
        </ul>
       
        
        <h3>販売価格</h3>
        <p>有料サービス提供時に、各サービスページに記載いたします。<br>
        価格はすべて日本円・税込み価格で表示いたします。</p>
        
        <h3>支払方法</h3>
        <p>有料サービス提供時に以下の方法を予定しています：</p>
        <ul>
            <li>クレジットカード決済</li>
            <li>PayPal決済</li>
            <li>その他、運営者が定める方法</li>
        </ul>
        
        <h3>支払時期</h3>
        <p>サービス申込み時に決済を行います。継続課金サービスの場合は、毎月または毎年の決済日に自動決済を行います。</p>
        
        <h3>サービス提供時期</h3>
        <p>決済完了後、即座にサービスをご利用いただけます。システムメンテナンス等により一時的にサービス提供が遅れる場合は、事前または事後に通知いたします。</p>
        
        <h3>返品・キャンセルについて</h3>
        <p>デジタルサービスの性質上、原則として返品・返金はお受けできません。ただし、以下の場合は例外とします：</p>
        <ul>
            <li>運営者側のシステム障害により、サービスが相当期間正常に提供できない場合</li>
            <li>決済システムの不具合により、重複して決済が行われた場合</li>
            <li>運営者の過失により、ユーザーに著しい不利益が生じたと運営者が判断した場合</li>
            <li>その他、運営者が合理的と判断する場合</li>
        </ul>
        
        <h4>返金処理について</h4>
        <p>返金が認められる場合、決済方法に応じて以下の処理を行います：</p>
        <ul>
            <li>クレジットカード決済：決済取消またはクレジットカード口座への返金</li>
            <li>PayPal決済：PayPalアカウントへの返金</li>
            <li>返金処理には、承認から5～10営業日程度を要する場合があります</li>
        </ul>
        
        <h3>サービス内容</h3>
        <p>水泳練習記録の管理・分析を支援するウェブサービスです。</p>
        <h4>基本サービス（無料）</h4>
        <ul>
            <li>練習記録の入力・保存</li>
            <li>基本的なデータ視覚化</li>
            <li>大会記録の管理</li>
        </ul>
        
        <h4>将来提供予定の有料サービス</h4>
        <ul>
            <li>プレミアム分析機能</li>
            <li>データエクスポート機能</li>
            <li>コーチング支援機能</li>
            <li>広告非表示オプション</li>
            <li>優先サポート</li>
        </ul>
        
        <h3>表現、及び商品に関する注意書き</h3>
        <p>本サービスは、水泳練習記録の管理を支援するものであり、以下の点にご注意ください：</p>
        <ul>
            <li>競技成績の向上を保証するものではありません</li>
            <li>医学的・健康的なアドバイスを提供するものではありません</li>
            <li>個人の体調や能力に関する判断は、専門家にご相談ください</li>
            <li>サービス利用による怪我や健康被害について、運営者は責任を負いません</li>
        </ul>
        
        <h3>動作環境</h3>
        <p>本サービスを快適にご利用いただくための推奨環境：</p>
        <ul>
            <li><strong>ブラウザ：</strong>Chrome、Firefox、Safari、Edge の最新版</li>
            <li><strong>OS：</strong>Windows 10以降、macOS 10.15以降、iOS 13以降、Android 8以降</li>
            <li><strong>インターネット接続：</strong>ブロードバンド環境推奨</li>
            <li><strong>JavaScript：</strong>有効である必要があります</li>
        </ul>
        
        <h3>個人情報について</h3>
        <p>お客様の個人情報については、当サイトの<a href="privacy.php">プライバシーポリシー</a>に従って適切に管理いたします。</p>
        
        <h3>知的財産権</h3>
        <p>本サービスに含まれるコンテンツ（テキスト、画像、プログラム等）の著作権その他の知的財産権は、運営者または正当な権利者に帰属します。</p>
        
        <h3>サービス利用に関する制限</h3>
        <p>以下に該当する方は、有料サービスをご利用いただけません：</p>
        <ul>
            <li>反社会的勢力に該当する方</li>
            <li>過去に利用規約違反歴のある方</li>
            <li>虚偽の情報でお申し込みをされた方</li>
            <li>支払能力に疑義がある方</li>
        </ul>
        
        <div class="highlight-box">
            <h4>⚠️ 将来の有料サービスについて</h4>
            <p>現在、本サービスは完全無料で提供していますが、有料サービスを将来的に提供する可能性があります。</p>
            <p>有料サービス提供開始時は、事前にユーザーの皆様にお知らせいたします。</p>
        </div>
        
        <h3>免責事項</h3>
        <p>運営者は、以下について一切の責任を負いません：</p>
        <ul>
            <li>天災、戦争、暴動、騒乱、労働争議等の不可抗力による損害</li>
            <li>ユーザーの機器やソフトウェア、インターネット接続の問題による損害</li>
            <li>第三者による本サービスへの不正アクセスや攻撃による損害</li>
            <li>ユーザーのID・パスワード管理不備による損害</li>
            <li>ユーザーが本サービスを利用して第三者との間で生じたトラブル</li>
        </ul>
        
        <div class="contact-info">
            <h4>📞 苦情・相談窓口</h4>
            <p>本サービスに関する苦情やご相談は、まず下記までご連絡ください：</p>
            <p><strong>メールアドレス：</strong>cre.eight12@gmail.com<br>
            <strong>件名：</strong>「サービスに関するお問い合わせ」と明記してください<br>
            <strong>回答時期：</strong>原則として1週間以内にご回答いたします</p>
            
            <p class="external-contact">解決しない場合は、以下の機関にご相談いただけます：</p>
            <ul>
                <li><strong>国民生活センター：</strong><a href="https://www.kokusen.go.jp/" target="_blank">https://www.kokusen.go.jp/</a></li>
                <li><strong>消費者ホットライン：</strong>188（いやや）</li>
                <li><strong>新潟県消費生活センター：</strong>025-285-4196</li>
                <li><strong>長岡市消費生活センター：</strong>0258-32-0022</li>
            </ul>
        </div>
        
        <h3>準拠法・裁判管轄</h3>
        <p>本表記および有料サービスに関する契約は、日本法に準拠します。有料サービスに関して紛争が生じた場合は、新潟地方裁判所長岡支部を第一審の専属的合意管轄裁判所とします。</p>
        
        <div class="business-info">
            <h4>🏢 事業者情報</h4>
            <p><strong>事業内容：</strong>ウェブサービスの企画・開発・運営<br>
            <strong>設立：</strong>2025年5月<br>
            <strong>ウェブサイト：</strong>https://swimlog.online/</p>
        </div>
        
        <div class="date-info">
            制定日：2025年5月24日<br>
            最終更新：2025年6月6日
        </div>
    </div>
    
    <div class="back-link">
        <a href="index.php">← SwimLogに戻る</a>
    </div>
</div>

<?php
// フッターの読み込み
include 'includes/footer.php';
?>

<script async src="https://www.googletagmanager.com/gtag/js?id=G-QMTKRPLHDD"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-QMTKRPLHDD');
</script>