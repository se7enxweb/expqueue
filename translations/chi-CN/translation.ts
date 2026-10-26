<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0" language="zh_CN">
<context>
    <name>design/admin/parts/setup/menu</name>
    <message>
        <source>Job queue</source>
        <translation>作业队列</translation>
    </message>
</context>
<context>
    <name>extension/expqueue</name>
    <message>
        <source>Job queue</source>
        <translation>作业队列</translation>
    </message>
    <message>
        <source>The tables of the job queue are missing in this database. Run ./console ext:expqueue:install-schema on the server.</source>
        <translation>此数据库中缺少作业队列的数据表。请在服务器上运行 ./console ext:expqueue:install-schema。</translation>
    </message>
    <message>
        <source>Dashboard</source>
        <translation>仪表板</translation>
    </message>
    <message>
        <source>The controls need the extension ezformtoken.</source>
        <translation>这些控件需要扩展 ezformtoken。</translation>
    </message>
    <message>
        <source>the queue</source>
        <translation>队列</translation>
    </message>
    <message>
        <source>Paused %what.</source>
        <translation>已暂停 %what。</translation>
    </message>
    <message>
        <source>Resumed %what.</source>
        <translation>已继续 %what。</translation>
    </message>
    <message>
        <source>Nothing changed.</source>
        <translation>未作任何更改。</translation>
    </message>
    <message>
        <source>The job had already ended.</source>
        <translation>该作业已经结束。</translation>
    </message>
    <message>
        <source>Cancel requested: the job stops at its next check.</source>
        <translation>已请求取消：作业将在下一次检查时停止。</translation>
    </message>
    <message>
        <source>The job is cancelled.</source>
        <translation>作业已取消。</translation>
    </message>
    <message>
        <source>The job is back in the queue.</source>
        <translation>作业已重新入队。</translation>
    </message>
    <message>
        <source>The job cannot be requeued.</source>
        <translation>无法重新入队该作业。</translation>
    </message>
    <message>
        <source>%count dead job(s) are back in the queue.</source>
        <translation>%count 个死信作业已重新入队。</translation>
    </message>
    <message>
        <source>The group is cancelled (%count job(s)).</source>
        <translation>该组已取消（%count 个作业）。</translation>
    </message>
    <message>
        <source>A test job is queued; a worker (cron within a minute, or the service) runs it.</source>
        <translation>已将一个测试作业加入队列；由工作进程（一分钟内的 cron 或服务）运行。</translation>
    </message>
    <message>
        <source>Unknown action.</source>
        <translation>未知的操作。</translation>
    </message>
    <message>
        <source>Group</source>
        <translation>组</translation>
    </message>
    <message>
        <source>Jobs</source>
        <translation>作业</translation>
    </message>
    <message>
        <source>Resume queue</source>
        <translation>继续队列</translation>
    </message>
    <message>
        <source>Pause queue</source>
        <translation>暂停队列</translation>
    </message>
    <message>
        <source>Pause the whole queue? Running jobs finish; no new job starts.</source>
        <translation>确定要暂停整个队列吗？正在运行的作业会完成，但不会启动新的作业。</translation>
    </message>
    <message>
        <source>Send a test job</source>
        <translation>发送测试作业</translation>
    </message>
    <message>
        <source>The queue is paused by the setting expqueue.ini [QueueSettings] Paused=enabled.</source>
        <translation>队列已由设置 expqueue.ini [QueueSettings] Paused=enabled 暂停。</translation>
    </message>
    <message>
        <source>Key figures</source>
        <translation>关键数据</translation>
    </message>
    <message>
        <source>Ready</source>
        <translation>就绪</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>运行中</translation>
    </message>
    <message>
        <source>Waiting or retrying</source>
        <translation>等待或重试中</translation>
    </message>
    <message>
        <source>Dead letter</source>
        <translation>死信</translation>
    </message>
    <message>
        <source>Done in the last hour</source>
        <translation>过去一小时内已完成</translation>
    </message>
    <message>
        <source>Oldest ready job</source>
        <translation>最早的就绪作业</translation>
    </message>
    <message>
        <source>Workers alive</source>
        <translation>存活的工作进程</translation>
    </message>
    <message>
        <source>Jobs ended per hour</source>
        <translation>每小时结束的作业</translation>
    </message>
    <message>
        <source>last 24 hours: %done done, %dead dead</source>
        <translation>过去 24 小时：%done 个已完成，%dead 个死信</translation>
    </message>
    <message>
        <source>No job ended in the last 24 hours.</source>
        <translation>过去 24 小时内没有作业结束。</translation>
    </message>
    <message>
        <source>Jobs ended per hour over the last 24 hours; the table below has the numbers.</source>
        <translation>过去 24 小时内每小时结束的作业；具体数字见下表。</translation>
    </message>
    <message>
        <source>%n done</source>
        <translation>%n 个已完成</translation>
    </message>
    <message>
        <source>%n dead</source>
        <translation>%n 个死信</translation>
    </message>
    <message>
        <source>peak %n per hour</source>
        <translation>峰值为每小时 %n 个</translation>
    </message>
    <message>
        <source>Show as a table</source>
        <translation>以表格显示</translation>
    </message>
    <message>
        <source>Hour</source>
        <translation>时</translation>
    </message>
    <message>
        <source>Priority classes</source>
        <translation>优先级类别</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>类</translation>
    </message>
    <message>
        <source>Weight</source>
        <translation>权重</translation>
    </message>
    <message>
        <source>Waiting</source>
        <translation>等待中</translation>
    </message>
    <message>
        <source>Dead</source>
        <translation>死信</translation>
    </message>
    <message>
        <source>Oldest</source>
        <translation>最早</translation>
    </message>
    <message>
        <source>p95 wait (1 h)</source>
        <translation>p95 等待时间（1 小时）</translation>
    </message>
    <message>
        <source>Done (1 h)</source>
        <translation>已完成（1 小时）</translation>
    </message>
    <message>
        <source>Controls</source>
        <translation>控制</translation>
    </message>
    <message>
        <source>reserved</source>
        <translation>已预留</translation>
    </message>
    <message>
        <source>Resume</source>
        <translation>继续</translation>
    </message>
    <message>
        <source>Pause</source>
        <translation>暂停</translation>
    </message>
    <message>
        <source>Resources</source>
        <translation>资源</translation>
    </message>
    <message>
        <source>No job names a resource yet.</source>
        <translation>尚无作业指定资源。</translation>
    </message>
    <message>
        <source>Resource</source>
        <translation>资源</translation>
    </message>
    <message>
        <source>Running / limit</source>
        <translation>运行中 / 上限</translation>
    </message>
    <message>
        <source>State</source>
        <translation>状态</translation>
    </message>
    <message>
        <source>max %n</source>
        <translation>最多 %n</translation>
    </message>
    <message>
        <source>cooldown %t</source>
        <translation>冷却 %t</translation>
    </message>
    <message>
        <source>last %s</source>
        <translation>上次 %s</translation>
    </message>
    <message>
        <source>ok</source>
        <translation>正常</translation>
    </message>
    <message>
        <source>Workers</source>
        <translation>工作进程</translation>
    </message>
    <message>
        <source>Cron part expqueue</source>
        <translation>Cron 部分 expqueue</translation>
    </message>
    <message>
        <source>ran %t ago</source>
        <translation>%t 前运行过</translation>
    </message>
    <message>
        <source>late: last run %t ago</source>
        <translation>已延迟：上次运行在 %t 前</translation>
    </message>
    <message>
        <source>never ran</source>
        <translation>从未运行</translation>
    </message>
    <message>
        <source>add php runcronjobs.php -q expqueue to the crontab (every minute)</source>
        <translation>请将 php runcronjobs.php -q expqueue 添加到 crontab（每分钟一次）</translation>
    </message>
    <message>
        <source>systemd service</source>
        <translation>systemd 服务</translation>
    </message>
    <message>
        <source>installed</source>
        <translation>已安装</translation>
    </message>
    <message>
        <source>%n worker(s) alive</source>
        <translation>%n 个工作进程存活</translation>
    </message>
    <message>
        <source>not installed: cron is the worker</source>
        <translation>未安装：由 cron 担任工作进程</translation>
    </message>
    <message>
        <source>Worker</source>
        <translation>工作进程</translation>
    </message>
    <message>
        <source>Started by</source>
        <translation>启动者</translation>
    </message>
    <message>
        <source>Up</source>
        <translation>运行时长</translation>
    </message>
    <message>
        <source>Heartbeat</source>
        <translation>心跳</translation>
    </message>
    <message>
        <source>Done</source>
        <translation>已完成</translation>
    </message>
    <message>
        <source>job %id</source>
        <translation>作业 %id</translation>
    </message>
    <message>
        <source>No worker is running right now; the next cron run starts one when jobs are ready.</source>
        <translation>当前没有运行中的工作进程；作业就绪后，下一次 cron 运行会启动一个。</translation>
    </message>
    <message>
        <source>Running now</source>
        <translation>正在运行</translation>
    </message>
    <message>
        <source>Nothing runs.</source>
        <translation>没有正在运行的作业。</translation>
    </message>
    <message>
        <source>all %n</source>
        <translation>全部 %n 个</translation>
    </message>
    <message>
        <source>Requeue all</source>
        <translation>全部重新入队</translation>
    </message>
    <message>
        <source>Put every dead job back into the queue with its attempts reset?</source>
        <translation>确定要将所有死信作业放回队列并重置其尝试次数吗？</translation>
    </message>
    <message>
        <source>Empty: no job has failed for good.</source>
        <translation>空：没有作业彻底失败。</translation>
    </message>
    <message>
        <source>Groups</source>
        <translation>组</translation>
    </message>
    <message>
        <source>No job group yet.</source>
        <translation>尚无作业组。</translation>
    </message>
    <message>
        <source>Cancel group</source>
        <translation>取消作业组</translation>
    </message>
    <message>
        <source>Cancel every job of this group that has not ended?</source>
        <translation>确定要取消此组中所有尚未结束的作业吗？</translation>
    </message>
    <message>
        <source>Progress</source>
        <translation>进度</translation>
    </message>
    <message>
        <source>%done of %total ended (%percent%)</source>
        <translation>%done 个已结束，共 %total 个（%percent%）</translation>
    </message>
    <message>
        <source>Id</source>
        <translation>ID</translation>
    </message>
    <message>
        <source>Type</source>
        <translation>类型</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>创建时间</translation>
    </message>
    <message>
        <source>The group has no job.</source>
        <translation>该组没有作业。</translation>
    </message>
    <message>
        <source>Newer</source>
        <translation>较新</translation>
    </message>
    <message>
        <source>Older</source>
        <translation>较旧</translation>
    </message>
    <message>
        <source>Cancel</source>
        <translation>取消</translation>
    </message>
    <message>
        <source>Cancel this job?</source>
        <translation>确定要取消此作业吗？</translation>
    </message>
    <message>
        <source>Requeue</source>
        <translation>重新入队</translation>
    </message>
    <message>
        <source>Job</source>
        <translation>作业</translation>
    </message>
    <message>
        <source>priority %p</source>
        <translation>优先级 %p</translation>
    </message>
    <message>
        <source>Attempts</source>
        <translation>尝试次数</translation>
    </message>
    <message>
        <source>timeout %t</source>
        <translation>超时 %t</translation>
    </message>
    <message>
        <source>Queued</source>
        <translation>已排队</translation>
    </message>
    <message>
        <source>%t ago</source>
        <translation>%t 前</translation>
    </message>
    <message>
        <source>Started</source>
        <translation>开始时间</translation>
    </message>
    <message>
        <source>waited %w, ran %r</source>
        <translation>等待 %w，运行 %r</translation>
    </message>
    <message>
        <source>Ended</source>
        <translation>已结束</translation>
    </message>
    <message>
        <source>Next attempt</source>
        <translation>下次尝试</translation>
    </message>
    <message>
        <source>in %t</source>
        <translation>%t 后</translation>
    </message>
    <message>
        <source>Lease</source>
        <translation>租约</translation>
    </message>
    <message>
        <source>%t left</source>
        <translation>剩余 %t</translation>
    </message>
    <message>
        <source>Queued by</source>
        <translation>入队者</translation>
    </message>
    <message>
        <source>Idempotency key</source>
        <translation>幂等键</translation>
    </message>
    <message>
        <source>After</source>
        <translation>之后</translation>
    </message>
    <message>
        <source>Lane</source>
        <translation>通道</translation>
    </message>
    <message>
        <source>Last error</source>
        <translation>最近的错误</translation>
    </message>
    <message>
        <source>Parameters</source>
        <translation>参数</translation>
    </message>
    <message>
        <source>None.</source>
        <translation>无。</translation>
    </message>
    <message>
        <source>Result</source>
        <translation>结果</translation>
    </message>
    <message>
        <source>checkpoint saved (%n bytes)</source>
        <translation>检查点已保存（%n 字节）</translation>
    </message>
    <message>
        <source>None yet.</source>
        <translation>暂无。</translation>
    </message>
    <message>
        <source>History</source>
        <translation>历史记录</translation>
    </message>
    <message>
        <source>%n event(s)</source>
        <translation>%n 个事件</translation>
    </message>
    <message>
        <source>Time</source>
        <translation>时间</translation>
    </message>
    <message>
        <source>Event</source>
        <translation>事件</translation>
    </message>
    <message>
        <source>Attempt</source>
        <translation>尝试</translation>
    </message>
    <message>
        <source>Message</source>
        <translation>消息</translation>
    </message>
    <message>
        <source>all</source>
        <translation>全部</translation>
    </message>
    <message>
        <source>Also</source>
        <translation>另外</translation>
    </message>
    <message>
        <source>type %t ×</source>
        <translation>类型 %t ×</translation>
    </message>
    <message>
        <source>resource %r ×</source>
        <translation>资源 %r ×</translation>
    </message>
    <message>
        <source>group %g</source>
        <translation>作业组 %g</translation>
    </message>
    <message>
        <source>Requeue all dead jobs</source>
        <translation>将所有死信作业重新入队</translation>
    </message>
    <message>
        <source>No job matches.</source>
        <translation>没有匹配的作业。</translation>
    </message>
    <message>
        <source>%from to %to</source>
        <translation>%from 至 %to</translation>
    </message>
    <message>
        <source>Priority</source>
        <translation>优先级</translation>
    </message>
    <message>
        <source>Age</source>
        <translation>存在时长</translation>
    </message>
    <message>
        <source>cancel requested</source>
        <translation>已请求取消</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/state</name>
    <message>
        <source>done</source>
        <translation>已完成</translation>
    </message>
    <message>
        <source>dead</source>
        <translation>死信</translation>
    </message>
    <message>
        <source>queued</source>
        <translation>已排队</translation>
    </message>
    <message>
        <source>ready</source>
        <translation>就绪</translation>
    </message>
    <message>
        <source>running</source>
        <translation>运行中</translation>
    </message>
    <message>
        <source>retry</source>
        <translation>重试</translation>
    </message>
    <message>
        <source>held</source>
        <translation>已暂挂</translation>
    </message>
    <message>
        <source>cancelled</source>
        <translation>已取消</translation>
    </message>
    <message>
        <source>active</source>
        <translation>活动</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>已暂停</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/rad</name>
    <message>
        <source>Queue job types</source>
        <translation>队列作业类型</translation>
    </message>
    <message>
        <source>The kinds of job a worker runs (expqueue.ini [JobType_&lt;type&gt;] Handler=, implementing expQueueHandler), the queue's own and every extension's.</source>
        <translation>工作进程运行的作业种类（expqueue.ini [JobType_&lt;type&gt;] Handler=，实现 expQueueHandler），包括队列自身的和每个扩展提供的。</translation>
    </message>
    <message>
        <source>Queue job type priority classes</source>
        <translation>队列作业类型优先级类别</translation>
    </message>
    <message>
        <source>The priority class each job type runs in (expqueue.ini [JobType_&lt;type&gt;] Class=, a [QueueClass_&lt;class&gt;] block); refers to the job types, so not added again.</source>
        <translation>每种作业类型所属的优先级类别（expqueue.ini [JobType_&lt;type&gt;] Class=，即某个 [QueueClass_&lt;class&gt;] 块）；引用的是作业类型，因此不再重复添加。</translation>
    </message>
    <message>
        <source>Queue priority classes</source>
        <translation>队列优先级类别</translation>
    </message>
    <message>
        <source>The lanes the scheduler shares the workers between (expqueue.ini [QueueSettings] Classes[], each with its [QueueClass_&lt;class&gt;]).</source>
        <translation>调度器在工作进程之间分配的通道（expqueue.ini [QueueSettings] Classes[]，每个都有各自的 [QueueClass_&lt;class&gt;]）。</translation>
    </message>
    <message>
        <source>Queue resources</source>
        <translation>队列资源</translation>
    </message>
    <message>
        <source>The resources whose concurrency the queue limits (expqueue.ini [QueueResource_&lt;resource&gt;]).</source>
        <translation>队列限制其并发数的资源（expqueue.ini [QueueResource_&lt;resource&gt;]）。</translation>
    </message>
    <message>
        <source>Queue commands</source>
        <translation>队列命令</translation>
    </message>
    <message>
        <source>The commands ./console ext:expqueue:&lt;name&gt; (expQueueCommand::COMMANDS).</source>
        <translation>命令 ./console ext:expqueue:&lt;name&gt;（expQueueCommand::COMMANDS）。</translation>
    </message>
</context>
</TS>
