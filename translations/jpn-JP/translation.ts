<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0" language="ja_JP">
<context>
    <name>design/admin/parts/setup/menu</name>
    <message>
        <source>Job queue</source>
        <translation>ジョブキュー</translation>
    </message>
</context>
<context>
    <name>extension/expqueue</name>
    <message>
        <source>Job queue</source>
        <translation>ジョブキュー</translation>
    </message>
    <message>
        <source>The tables of the job queue are missing in this database. Run ./console ext:expqueue:install-schema on the server.</source>
        <translation>このデータベースにジョブキューのテーブルがありません。サーバー上で ./console ext:expqueue:install-schema を実行してください。</translation>
    </message>
    <message>
        <source>Dashboard</source>
        <translation>ダッシュボード</translation>
    </message>
    <message>
        <source>The controls need the extension ezformtoken.</source>
        <translation>コントロールには拡張機能 ezformtoken が必要です。</translation>
    </message>
    <message>
        <source>the queue</source>
        <translation>キュー</translation>
    </message>
    <message>
        <source>Paused %what.</source>
        <translation>%what を一時停止しました。</translation>
    </message>
    <message>
        <source>Resumed %what.</source>
        <translation>%what を再開しました。</translation>
    </message>
    <message>
        <source>Nothing changed.</source>
        <translation>何も変更されていません。</translation>
    </message>
    <message>
        <source>The job had already ended.</source>
        <translation>ジョブはすでに終了していました。</translation>
    </message>
    <message>
        <source>Cancel requested: the job stops at its next check.</source>
        <translation>キャンセルを要求しました: ジョブは次のチェック時に停止します。</translation>
    </message>
    <message>
        <source>The job is cancelled.</source>
        <translation>ジョブはキャンセルされました。</translation>
    </message>
    <message>
        <source>The job is back in the queue.</source>
        <translation>ジョブはキューに戻されました。</translation>
    </message>
    <message>
        <source>The job cannot be requeued.</source>
        <translation>ジョブをキューに戻すことはできません。</translation>
    </message>
    <message>
        <source>%count dead job(s) are back in the queue.</source>
        <translation>デッドレターの %count 件のジョブがキューに戻されました。</translation>
    </message>
    <message>
        <source>The group is cancelled (%count job(s)).</source>
        <translation>グループはキャンセルされました（%count 件のジョブ）。</translation>
    </message>
    <message>
        <source>A test job is queued; a worker (cron within a minute, or the service) runs it.</source>
        <translation>テストジョブがキューに追加されました。ワーカー（1 分以内の cron、またはサービス）がこれを実行します。</translation>
    </message>
    <message>
        <source>Unknown action.</source>
        <translation>不明な操作です。</translation>
    </message>
    <message>
        <source>Group</source>
        <translation>グループ</translation>
    </message>
    <message>
        <source>Jobs</source>
        <translation>ジョブ</translation>
    </message>
    <message>
        <source>Resume queue</source>
        <translation>キューの再開</translation>
    </message>
    <message>
        <source>Pause queue</source>
        <translation>キューの一時停止</translation>
    </message>
    <message>
        <source>Pause the whole queue? Running jobs finish; no new job starts.</source>
        <translation>キュー全体を一時停止してもよろしいですか？実行中のジョブは完了しますが、新しいジョブは開始されません。</translation>
    </message>
    <message>
        <source>Send a test job</source>
        <translation>テストジョブの送信</translation>
    </message>
    <message>
        <source>The queue is paused by the setting expqueue.ini [QueueSettings] Paused=enabled.</source>
        <translation>キューは設定 expqueue.ini [QueueSettings] Paused=enabled によって一時停止されています。</translation>
    </message>
    <message>
        <source>Key figures</source>
        <translation>主要な数値</translation>
    </message>
    <message>
        <source>Ready</source>
        <translation>準備完了</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>実行中</translation>
    </message>
    <message>
        <source>Waiting or retrying</source>
        <translation>待機中または再試行中</translation>
    </message>
    <message>
        <source>Dead letter</source>
        <translation>デッドレター</translation>
    </message>
    <message>
        <source>Done in the last hour</source>
        <translation>過去 1 時間に完了</translation>
    </message>
    <message>
        <source>Oldest ready job</source>
        <translation>最も古い準備完了ジョブ</translation>
    </message>
    <message>
        <source>Workers alive</source>
        <translation>稼働中のワーカー</translation>
    </message>
    <message>
        <source>Jobs ended per hour</source>
        <translation>1 時間あたりの終了したジョブ</translation>
    </message>
    <message>
        <source>last 24 hours: %done done, %dead dead</source>
        <translation>過去 24 時間: 完了 %done 件、デッドレター %dead 件</translation>
    </message>
    <message>
        <source>No job ended in the last 24 hours.</source>
        <translation>過去 24 時間に終了したジョブはありません。</translation>
    </message>
    <message>
        <source>Jobs ended per hour over the last 24 hours; the table below has the numbers.</source>
        <translation>過去 24 時間の 1 時間あたりの終了したジョブ。数値は下の表にあります。</translation>
    </message>
    <message>
        <source>%n done</source>
        <translation>完了 %n 件</translation>
    </message>
    <message>
        <source>%n dead</source>
        <translation>デッドレター %n 件</translation>
    </message>
    <message>
        <source>peak %n per hour</source>
        <translation>ピーク: 1 時間あたり %n 件</translation>
    </message>
    <message>
        <source>Show as a table</source>
        <translation>表として表示</translation>
    </message>
    <message>
        <source>Hour</source>
        <translation>時</translation>
    </message>
    <message>
        <source>Priority classes</source>
        <translation>優先度クラス</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>クラス</translation>
    </message>
    <message>
        <source>Weight</source>
        <translation>重み</translation>
    </message>
    <message>
        <source>Waiting</source>
        <translation>待機中</translation>
    </message>
    <message>
        <source>Dead</source>
        <translation>デッドレター</translation>
    </message>
    <message>
        <source>Oldest</source>
        <translation>最も古い</translation>
    </message>
    <message>
        <source>p95 wait (1 h)</source>
        <translation>p95 待機時間（1 時間）</translation>
    </message>
    <message>
        <source>Done (1 h)</source>
        <translation>完了（1 時間）</translation>
    </message>
    <message>
        <source>Controls</source>
        <translation>コントロール</translation>
    </message>
    <message>
        <source>reserved</source>
        <translation>予約済み</translation>
    </message>
    <message>
        <source>Resume</source>
        <translation>再開</translation>
    </message>
    <message>
        <source>Pause</source>
        <translation>一時停止</translation>
    </message>
    <message>
        <source>Resources</source>
        <translation>リソース</translation>
    </message>
    <message>
        <source>No job names a resource yet.</source>
        <translation>リソースを指定しているジョブはまだありません。</translation>
    </message>
    <message>
        <source>Resource</source>
        <translation>リソース</translation>
    </message>
    <message>
        <source>Running / limit</source>
        <translation>実行中 / 上限</translation>
    </message>
    <message>
        <source>State</source>
        <translation>状態</translation>
    </message>
    <message>
        <source>max %n</source>
        <translation>最大 %n</translation>
    </message>
    <message>
        <source>cooldown %t</source>
        <translation>クールダウン %t</translation>
    </message>
    <message>
        <source>last %s</source>
        <translation>最終 %s</translation>
    </message>
    <message>
        <source>ok</source>
        <translation>正常</translation>
    </message>
    <message>
        <source>Workers</source>
        <translation>ワーカー</translation>
    </message>
    <message>
        <source>Cron part expqueue</source>
        <translation>cron パート expqueue</translation>
    </message>
    <message>
        <source>ran %t ago</source>
        <translation>%t 前に実行</translation>
    </message>
    <message>
        <source>late: last run %t ago</source>
        <translation>遅延: 最終実行は %t 前</translation>
    </message>
    <message>
        <source>never ran</source>
        <translation>未実行</translation>
    </message>
    <message>
        <source>add php runcronjobs.php -q expqueue to the crontab (every minute)</source>
        <translation>php runcronjobs.php -q expqueue を crontab に追加してください（毎分）</translation>
    </message>
    <message>
        <source>systemd service</source>
        <translation>systemd サービス</translation>
    </message>
    <message>
        <source>installed</source>
        <translation>インストール済み</translation>
    </message>
    <message>
        <source>%n worker(s) alive</source>
        <translation>%n 個のワーカーが稼働中</translation>
    </message>
    <message>
        <source>not installed: cron is the worker</source>
        <translation>未インストール: cron がワーカーとして動作</translation>
    </message>
    <message>
        <source>Worker</source>
        <translation>ワーカー</translation>
    </message>
    <message>
        <source>Started by</source>
        <translation>開始したユーザー</translation>
    </message>
    <message>
        <source>Up</source>
        <translation>稼働時間</translation>
    </message>
    <message>
        <source>Heartbeat</source>
        <translation>ハートビート</translation>
    </message>
    <message>
        <source>Done</source>
        <translation>完了</translation>
    </message>
    <message>
        <source>job %id</source>
        <translation>ジョブ %id</translation>
    </message>
    <message>
        <source>No worker is running right now; the next cron run starts one when jobs are ready.</source>
        <translation>現在実行中のワーカーはありません。ジョブの準備ができると、次の cron の実行でワーカーが起動されます。</translation>
    </message>
    <message>
        <source>Running now</source>
        <translation>現在実行中</translation>
    </message>
    <message>
        <source>Nothing runs.</source>
        <translation>実行中のものはありません。</translation>
    </message>
    <message>
        <source>all %n</source>
        <translation>すべて（%n）</translation>
    </message>
    <message>
        <source>Requeue all</source>
        <translation>すべてをキューに戻す</translation>
    </message>
    <message>
        <source>Put every dead job back into the queue with its attempts reset?</source>
        <translation>すべてのデッドレターのジョブを、試行回数をリセットしてキューに戻してもよろしいですか？</translation>
    </message>
    <message>
        <source>Empty: no job has failed for good.</source>
        <translation>空: 最終的に失敗したジョブはありません。</translation>
    </message>
    <message>
        <source>Groups</source>
        <translation>グループ</translation>
    </message>
    <message>
        <source>No job group yet.</source>
        <translation>ジョブグループはまだありません。</translation>
    </message>
    <message>
        <source>Cancel group</source>
        <translation>グループのキャンセル</translation>
    </message>
    <message>
        <source>Cancel every job of this group that has not ended?</source>
        <translation>このグループの終了していないすべてのジョブをキャンセルしてもよろしいですか？</translation>
    </message>
    <message>
        <source>Progress</source>
        <translation>進行状況</translation>
    </message>
    <message>
        <source>%done of %total ended (%percent%)</source>
        <translation>%total 件中 %done 件が終了（%percent%）</translation>
    </message>
    <message>
        <source>Id</source>
        <translation>ID</translation>
    </message>
    <message>
        <source>Type</source>
        <translation>種類</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>作成日時</translation>
    </message>
    <message>
        <source>The group has no job.</source>
        <translation>このグループにはジョブがありません。</translation>
    </message>
    <message>
        <source>Newer</source>
        <translation>新しいもの</translation>
    </message>
    <message>
        <source>Older</source>
        <translation>古いもの</translation>
    </message>
    <message>
        <source>Cancel</source>
        <translation>キャンセル</translation>
    </message>
    <message>
        <source>Cancel this job?</source>
        <translation>このジョブをキャンセルしてもよろしいですか？</translation>
    </message>
    <message>
        <source>Requeue</source>
        <translation>キューに戻す</translation>
    </message>
    <message>
        <source>Job</source>
        <translation>ジョブ</translation>
    </message>
    <message>
        <source>priority %p</source>
        <translation>優先度 %p</translation>
    </message>
    <message>
        <source>Attempts</source>
        <translation>試行回数</translation>
    </message>
    <message>
        <source>timeout %t</source>
        <translation>タイムアウト %t</translation>
    </message>
    <message>
        <source>Queued</source>
        <translation>キュー登録済み</translation>
    </message>
    <message>
        <source>%t ago</source>
        <translation>%t 前</translation>
    </message>
    <message>
        <source>Started</source>
        <translation>開始日時</translation>
    </message>
    <message>
        <source>waited %w, ran %r</source>
        <translation>待機 %w、実行 %r</translation>
    </message>
    <message>
        <source>Ended</source>
        <translation>終了</translation>
    </message>
    <message>
        <source>Next attempt</source>
        <translation>次の試行</translation>
    </message>
    <message>
        <source>in %t</source>
        <translation>%t 後</translation>
    </message>
    <message>
        <source>Lease</source>
        <translation>リース</translation>
    </message>
    <message>
        <source>%t left</source>
        <translation>残り %t</translation>
    </message>
    <message>
        <source>Queued by</source>
        <translation>キューへの追加者</translation>
    </message>
    <message>
        <source>Idempotency key</source>
        <translation>冪等性キー</translation>
    </message>
    <message>
        <source>After</source>
        <translation>後</translation>
    </message>
    <message>
        <source>Lane</source>
        <translation>レーン</translation>
    </message>
    <message>
        <source>Last error</source>
        <translation>最後のエラー</translation>
    </message>
    <message>
        <source>Parameters</source>
        <translation>パラメーター</translation>
    </message>
    <message>
        <source>None.</source>
        <translation>なし。</translation>
    </message>
    <message>
        <source>Result</source>
        <translation>結果</translation>
    </message>
    <message>
        <source>checkpoint saved (%n bytes)</source>
        <translation>チェックポイントを保存済み（%n バイト）</translation>
    </message>
    <message>
        <source>None yet.</source>
        <translation>まだありません。</translation>
    </message>
    <message>
        <source>History</source>
        <translation>履歴</translation>
    </message>
    <message>
        <source>%n event(s)</source>
        <translation>%n 件のイベント</translation>
    </message>
    <message>
        <source>Time</source>
        <translation>時刻</translation>
    </message>
    <message>
        <source>Event</source>
        <translation>イベント</translation>
    </message>
    <message>
        <source>Attempt</source>
        <translation>試行</translation>
    </message>
    <message>
        <source>Message</source>
        <translation>メッセージ</translation>
    </message>
    <message>
        <source>all</source>
        <translation>すべて</translation>
    </message>
    <message>
        <source>Also</source>
        <translation>その他</translation>
    </message>
    <message>
        <source>type %t ×</source>
        <translation>種類 %t ×</translation>
    </message>
    <message>
        <source>resource %r ×</source>
        <translation>リソース %r ×</translation>
    </message>
    <message>
        <source>group %g</source>
        <translation>グループ %g</translation>
    </message>
    <message>
        <source>Requeue all dead jobs</source>
        <translation>すべてのデッドレターのジョブをキューに戻す</translation>
    </message>
    <message>
        <source>No job matches.</source>
        <translation>一致するジョブはありません。</translation>
    </message>
    <message>
        <source>%from to %to</source>
        <translation>%from ～ %to</translation>
    </message>
    <message>
        <source>Priority</source>
        <translation>優先度</translation>
    </message>
    <message>
        <source>Age</source>
        <translation>経過時間</translation>
    </message>
    <message>
        <source>cancel requested</source>
        <translation>キャンセル要求済み</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/state</name>
    <message>
        <source>done</source>
        <translation>完了</translation>
    </message>
    <message>
        <source>dead</source>
        <translation>デッドレター</translation>
    </message>
    <message>
        <source>queued</source>
        <translation>キュー登録済み</translation>
    </message>
    <message>
        <source>ready</source>
        <translation>準備完了</translation>
    </message>
    <message>
        <source>running</source>
        <translation>実行中</translation>
    </message>
    <message>
        <source>retry</source>
        <translation>再試行</translation>
    </message>
    <message>
        <source>held</source>
        <translation>保留</translation>
    </message>
    <message>
        <source>cancelled</source>
        <translation>キャンセル済み</translation>
    </message>
    <message>
        <source>active</source>
        <translation>アクティブ</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>一時停止中</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/rad</name>
    <message>
        <source>Queue job types</source>
        <translation>キューのジョブの種類</translation>
    </message>
    <message>
        <source>The kinds of job a worker runs (expqueue.ini [JobType_&lt;type&gt;] Handler=, implementing expQueueHandler), the queue's own and every extension's.</source>
        <translation>ワーカーが実行するジョブの種類（expqueue.ini [JobType_&lt;type&gt;] Handler=、expQueueHandler を実装）。キュー自体のものと各拡張機能のものを含みます。</translation>
    </message>
    <message>
        <source>Queue job type priority classes</source>
        <translation>ジョブの種類の優先度クラス</translation>
    </message>
    <message>
        <source>The priority class each job type runs in (expqueue.ini [JobType_&lt;type&gt;] Class=, a [QueueClass_&lt;class&gt;] block); refers to the job types, so not added again.</source>
        <translation>各ジョブの種類が実行される優先度クラス（expqueue.ini [JobType_&lt;type&gt;] Class=、[QueueClass_&lt;class&gt;] ブロック）。ジョブの種類を参照するため、重複して加算されません。</translation>
    </message>
    <message>
        <source>Queue priority classes</source>
        <translation>キューの優先度クラス</translation>
    </message>
    <message>
        <source>The lanes the scheduler shares the workers between (expqueue.ini [QueueSettings] Classes[], each with its [QueueClass_&lt;class&gt;]).</source>
        <translation>スケジューラーがワーカーを分配するレーン（expqueue.ini [QueueSettings] Classes[]、それぞれに [QueueClass_&lt;class&gt;]）。</translation>
    </message>
    <message>
        <source>Queue resources</source>
        <translation>キューのリソース</translation>
    </message>
    <message>
        <source>The resources whose concurrency the queue limits (expqueue.ini [QueueResource_&lt;resource&gt;]).</source>
        <translation>キューが同時実行数を制限するリソース（expqueue.ini [QueueResource_&lt;resource&gt;]）。</translation>
    </message>
    <message>
        <source>Queue commands</source>
        <translation>キューのコマンド</translation>
    </message>
    <message>
        <source>The commands ./console ext:expqueue:&lt;name&gt; (expQueueCommand::COMMANDS).</source>
        <translation>./console ext:expqueue:&lt;name&gt; コマンド（expQueueCommand::COMMANDS）。</translation>
    </message>
</context>
</TS>
