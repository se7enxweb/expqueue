<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0" language="ko_KR">
<context>
    <name>design/admin/parts/setup/menu</name>
    <message>
        <source>Job queue</source>
        <translation>작업 큐</translation>
    </message>
</context>
<context>
    <name>extension/expqueue</name>
    <message>
        <source>Job queue</source>
        <translation>작업 큐</translation>
    </message>
    <message>
        <source>The tables of the job queue are missing in this database. Run ./console ext:expqueue:install-schema on the server.</source>
        <translation>이 데이터베이스에 작업 큐 테이블이 없습니다. 서버에서 ./console ext:expqueue:install-schema를 실행하십시오.</translation>
    </message>
    <message>
        <source>Dashboard</source>
        <translation>대시보드</translation>
    </message>
    <message>
        <source>The controls need the extension ezformtoken.</source>
        <translation>컨트롤을 사용하려면 ezformtoken 확장이 필요합니다.</translation>
    </message>
    <message>
        <source>the queue</source>
        <translation>큐</translation>
    </message>
    <message>
        <source>Paused %what.</source>
        <translation>일시 중지됨: %what.</translation>
    </message>
    <message>
        <source>Resumed %what.</source>
        <translation>다시 시작됨: %what.</translation>
    </message>
    <message>
        <source>Nothing changed.</source>
        <translation>변경된 내용이 없습니다.</translation>
    </message>
    <message>
        <source>The job had already ended.</source>
        <translation>작업이 이미 종료되었습니다.</translation>
    </message>
    <message>
        <source>Cancel requested: the job stops at its next check.</source>
        <translation>취소가 요청되었습니다. 작업은 다음 검사 시점에 중지됩니다.</translation>
    </message>
    <message>
        <source>The job is cancelled.</source>
        <translation>작업이 취소되었습니다.</translation>
    </message>
    <message>
        <source>The job is back in the queue.</source>
        <translation>작업이 다시 큐에 들어갔습니다.</translation>
    </message>
    <message>
        <source>The job cannot be requeued.</source>
        <translation>작업을 다시 큐에 넣을 수 없습니다.</translation>
    </message>
    <message>
        <source>%count dead job(s) are back in the queue.</source>
        <translation>영구 실패한 작업 %count개가 다시 큐에 들어갔습니다.</translation>
    </message>
    <message>
        <source>The group is cancelled (%count job(s)).</source>
        <translation>그룹이 취소되었습니다(작업 %count개).</translation>
    </message>
    <message>
        <source>A test job is queued; a worker (cron within a minute, or the service) runs it.</source>
        <translation>테스트 작업이 큐에 추가되었습니다. 작업자(1분 이내의 크론 또는 서비스)가 실행합니다.</translation>
    </message>
    <message>
        <source>Unknown action.</source>
        <translation>알 수 없는 작업입니다.</translation>
    </message>
    <message>
        <source>Group</source>
        <translation>그룹</translation>
    </message>
    <message>
        <source>Jobs</source>
        <translation>작업</translation>
    </message>
    <message>
        <source>Resume queue</source>
        <translation>큐 다시 시작</translation>
    </message>
    <message>
        <source>Pause queue</source>
        <translation>큐 일시 중지</translation>
    </message>
    <message>
        <source>Pause the whole queue? Running jobs finish; no new job starts.</source>
        <translation>전체 큐를 일시 중지하시겠습니까? 실행 중인 작업은 완료되며 새 작업은 시작되지 않습니다.</translation>
    </message>
    <message>
        <source>Send a test job</source>
        <translation>테스트 작업 보내기</translation>
    </message>
    <message>
        <source>The queue is paused by the setting expqueue.ini [QueueSettings] Paused=enabled.</source>
        <translation>expqueue.ini [QueueSettings] Paused=enabled 설정으로 큐가 일시 중지되어 있습니다.</translation>
    </message>
    <message>
        <source>Key figures</source>
        <translation>주요 수치</translation>
    </message>
    <message>
        <source>Ready</source>
        <translation>준비됨</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>실행 중</translation>
    </message>
    <message>
        <source>Waiting or retrying</source>
        <translation>대기 중 또는 다시 시도 중</translation>
    </message>
    <message>
        <source>Dead letter</source>
        <translation>배달 못한 편지 큐</translation>
    </message>
    <message>
        <source>Done in the last hour</source>
        <translation>지난 1시간 동안 완료됨</translation>
    </message>
    <message>
        <source>Oldest ready job</source>
        <translation>가장 오래된 준비 작업</translation>
    </message>
    <message>
        <source>Workers alive</source>
        <translation>활성 작업자</translation>
    </message>
    <message>
        <source>Jobs ended per hour</source>
        <translation>시간당 종료된 작업</translation>
    </message>
    <message>
        <source>last 24 hours: %done done, %dead dead</source>
        <translation>지난 24시간: 완료 %done개, 영구 실패 %dead개</translation>
    </message>
    <message>
        <source>No job ended in the last 24 hours.</source>
        <translation>지난 24시간 동안 종료된 작업이 없습니다.</translation>
    </message>
    <message>
        <source>Jobs ended per hour over the last 24 hours; the table below has the numbers.</source>
        <translation>지난 24시간 동안 시간당 종료된 작업입니다. 숫자는 아래 표에 있습니다.</translation>
    </message>
    <message>
        <source>%n done</source>
        <translation>완료 %n개</translation>
    </message>
    <message>
        <source>%n dead</source>
        <translation>영구 실패 %n개</translation>
    </message>
    <message>
        <source>peak %n per hour</source>
        <translation>최대 시간당 %n개</translation>
    </message>
    <message>
        <source>Show as a table</source>
        <translation>표로 보기</translation>
    </message>
    <message>
        <source>Hour</source>
        <translation>시</translation>
    </message>
    <message>
        <source>Priority classes</source>
        <translation>우선순위 클래스</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>클래스</translation>
    </message>
    <message>
        <source>Weight</source>
        <translation>가중치</translation>
    </message>
    <message>
        <source>Waiting</source>
        <translation>대기 중</translation>
    </message>
    <message>
        <source>Dead</source>
        <translation>영구 실패</translation>
    </message>
    <message>
        <source>Oldest</source>
        <translation>가장 오래된 항목</translation>
    </message>
    <message>
        <source>p95 wait (1 h)</source>
        <translation>p95 대기 시간(1시간)</translation>
    </message>
    <message>
        <source>Done (1 h)</source>
        <translation>완료(1시간)</translation>
    </message>
    <message>
        <source>Controls</source>
        <translation>제어</translation>
    </message>
    <message>
        <source>reserved</source>
        <translation>예약됨</translation>
    </message>
    <message>
        <source>Resume</source>
        <translation>다시 시작</translation>
    </message>
    <message>
        <source>Pause</source>
        <translation>일시 중지</translation>
    </message>
    <message>
        <source>Resources</source>
        <translation>리소스</translation>
    </message>
    <message>
        <source>No job names a resource yet.</source>
        <translation>아직 리소스를 지정한 작업이 없습니다.</translation>
    </message>
    <message>
        <source>Resource</source>
        <translation>리소스</translation>
    </message>
    <message>
        <source>Running / limit</source>
        <translation>실행 중 / 한도</translation>
    </message>
    <message>
        <source>State</source>
        <translation>상태</translation>
    </message>
    <message>
        <source>max %n</source>
        <translation>최대 %n</translation>
    </message>
    <message>
        <source>cooldown %t</source>
        <translation>재사용 대기 %t</translation>
    </message>
    <message>
        <source>last %s</source>
        <translation>마지막 %s</translation>
    </message>
    <message>
        <source>ok</source>
        <translation>정상</translation>
    </message>
    <message>
        <source>Workers</source>
        <translation>작업자</translation>
    </message>
    <message>
        <source>Cron part expqueue</source>
        <translation>expqueue 크론 작업 부분</translation>
    </message>
    <message>
        <source>ran %t ago</source>
        <translation>%t 전에 실행됨</translation>
    </message>
    <message>
        <source>late: last run %t ago</source>
        <translation>지연됨: 마지막 실행 %t 전</translation>
    </message>
    <message>
        <source>never ran</source>
        <translation>실행된 적 없음</translation>
    </message>
    <message>
        <source>add php runcronjobs.php -q expqueue to the crontab (every minute)</source>
        <translation>crontab에 php runcronjobs.php -q expqueue를 추가하십시오(1분마다)</translation>
    </message>
    <message>
        <source>systemd service</source>
        <translation>systemd 서비스</translation>
    </message>
    <message>
        <source>installed</source>
        <translation>설치됨</translation>
    </message>
    <message>
        <source>%n worker(s) alive</source>
        <translation>활성 작업자 %n개</translation>
    </message>
    <message>
        <source>not installed: cron is the worker</source>
        <translation>설치되지 않음: 크론이 작업자 역할을 함</translation>
    </message>
    <message>
        <source>Worker</source>
        <translation>작업자</translation>
    </message>
    <message>
        <source>Started by</source>
        <translation>시작한 사용자</translation>
    </message>
    <message>
        <source>Up</source>
        <translation>가동 시간</translation>
    </message>
    <message>
        <source>Heartbeat</source>
        <translation>하트비트</translation>
    </message>
    <message>
        <source>Done</source>
        <translation>완료</translation>
    </message>
    <message>
        <source>job %id</source>
        <translation>작업 %id</translation>
    </message>
    <message>
        <source>No worker is running right now; the next cron run starts one when jobs are ready.</source>
        <translation>현재 실행 중인 작업자가 없습니다. 작업이 준비되면 다음 크론 실행에서 작업자를 시작합니다.</translation>
    </message>
    <message>
        <source>Running now</source>
        <translation>현재 실행 중</translation>
    </message>
    <message>
        <source>Nothing runs.</source>
        <translation>실행 중인 항목이 없습니다.</translation>
    </message>
    <message>
        <source>all %n</source>
        <translation>전체 %n개</translation>
    </message>
    <message>
        <source>Requeue all</source>
        <translation>모두 다시 큐에 넣기</translation>
    </message>
    <message>
        <source>Put every dead job back into the queue with its attempts reset?</source>
        <translation>영구 실패한 모든 작업의 시도 횟수를 초기화하고 다시 큐에 넣으시겠습니까?</translation>
    </message>
    <message>
        <source>Empty: no job has failed for good.</source>
        <translation>비어 있음: 영구적으로 실패한 작업이 없습니다.</translation>
    </message>
    <message>
        <source>Groups</source>
        <translation>그룹</translation>
    </message>
    <message>
        <source>No job group yet.</source>
        <translation>아직 작업 그룹이 없습니다.</translation>
    </message>
    <message>
        <source>Cancel group</source>
        <translation>그룹 취소</translation>
    </message>
    <message>
        <source>Cancel every job of this group that has not ended?</source>
        <translation>이 그룹에서 종료되지 않은 모든 작업을 취소하시겠습니까?</translation>
    </message>
    <message>
        <source>Progress</source>
        <translation>진행률</translation>
    </message>
    <message>
        <source>%done of %total ended (%percent%)</source>
        <translation>%total개 중 %done개 종료됨(%percent%)</translation>
    </message>
    <message>
        <source>Id</source>
        <translation>ID</translation>
    </message>
    <message>
        <source>Type</source>
        <translation>유형</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>생성일</translation>
    </message>
    <message>
        <source>The group has no job.</source>
        <translation>그룹에 작업이 없습니다.</translation>
    </message>
    <message>
        <source>Newer</source>
        <translation>최신</translation>
    </message>
    <message>
        <source>Older</source>
        <translation>이전</translation>
    </message>
    <message>
        <source>Cancel</source>
        <translation>취소</translation>
    </message>
    <message>
        <source>Cancel this job?</source>
        <translation>이 작업을 취소하시겠습니까?</translation>
    </message>
    <message>
        <source>Requeue</source>
        <translation>다시 큐에 넣기</translation>
    </message>
    <message>
        <source>Job</source>
        <translation>작업</translation>
    </message>
    <message>
        <source>priority %p</source>
        <translation>우선순위 %p</translation>
    </message>
    <message>
        <source>Attempts</source>
        <translation>시도 횟수</translation>
    </message>
    <message>
        <source>timeout %t</source>
        <translation>시간 제한 %t</translation>
    </message>
    <message>
        <source>Queued</source>
        <translation>대기 중</translation>
    </message>
    <message>
        <source>%t ago</source>
        <translation>%t 전</translation>
    </message>
    <message>
        <source>Started</source>
        <translation>시작 시간</translation>
    </message>
    <message>
        <source>waited %w, ran %r</source>
        <translation>대기 %w, 실행 %r</translation>
    </message>
    <message>
        <source>Ended</source>
        <translation>종료됨</translation>
    </message>
    <message>
        <source>Next attempt</source>
        <translation>다음 시도</translation>
    </message>
    <message>
        <source>in %t</source>
        <translation>%t 후</translation>
    </message>
    <message>
        <source>Lease</source>
        <translation>임대</translation>
    </message>
    <message>
        <source>%t left</source>
        <translation>%t 남음</translation>
    </message>
    <message>
        <source>Queued by</source>
        <translation>큐에 추가한 사람</translation>
    </message>
    <message>
        <source>Idempotency key</source>
        <translation>멱등성 키</translation>
    </message>
    <message>
        <source>After</source>
        <translation>이후</translation>
    </message>
    <message>
        <source>Lane</source>
        <translation>레인</translation>
    </message>
    <message>
        <source>Last error</source>
        <translation>마지막 오류</translation>
    </message>
    <message>
        <source>Parameters</source>
        <translation>매개 변수</translation>
    </message>
    <message>
        <source>None.</source>
        <translation>없음.</translation>
    </message>
    <message>
        <source>Result</source>
        <translation>결과</translation>
    </message>
    <message>
        <source>checkpoint saved (%n bytes)</source>
        <translation>검사점 저장됨(%n바이트)</translation>
    </message>
    <message>
        <source>None yet.</source>
        <translation>아직 없습니다.</translation>
    </message>
    <message>
        <source>History</source>
        <translation>기록</translation>
    </message>
    <message>
        <source>%n event(s)</source>
        <translation>이벤트 %n개</translation>
    </message>
    <message>
        <source>Time</source>
        <translation>시간</translation>
    </message>
    <message>
        <source>Event</source>
        <translation>이벤트</translation>
    </message>
    <message>
        <source>Attempt</source>
        <translation>시도</translation>
    </message>
    <message>
        <source>Message</source>
        <translation>메시지</translation>
    </message>
    <message>
        <source>all</source>
        <translation>모두</translation>
    </message>
    <message>
        <source>Also</source>
        <translation>추가 조건</translation>
    </message>
    <message>
        <source>type %t ×</source>
        <translation>유형 %t ×</translation>
    </message>
    <message>
        <source>resource %r ×</source>
        <translation>리소스 %r ×</translation>
    </message>
    <message>
        <source>group %g</source>
        <translation>그룹 %g</translation>
    </message>
    <message>
        <source>Requeue all dead jobs</source>
        <translation>영구 실패한 작업 모두 다시 큐에 넣기</translation>
    </message>
    <message>
        <source>No job matches.</source>
        <translation>일치하는 작업이 없습니다.</translation>
    </message>
    <message>
        <source>%from to %to</source>
        <translation>%from~%to</translation>
    </message>
    <message>
        <source>Priority</source>
        <translation>우선순위</translation>
    </message>
    <message>
        <source>Age</source>
        <translation>경과 기간</translation>
    </message>
    <message>
        <source>cancel requested</source>
        <translation>취소 요청됨</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/state</name>
    <message>
        <source>done</source>
        <translation>완료</translation>
    </message>
    <message>
        <source>dead</source>
        <translation>영구 실패</translation>
    </message>
    <message>
        <source>queued</source>
        <translation>큐 대기 중</translation>
    </message>
    <message>
        <source>ready</source>
        <translation>준비됨</translation>
    </message>
    <message>
        <source>running</source>
        <translation>실행 중</translation>
    </message>
    <message>
        <source>retry</source>
        <translation>다시 시도</translation>
    </message>
    <message>
        <source>held</source>
        <translation>보류됨</translation>
    </message>
    <message>
        <source>cancelled</source>
        <translation>취소됨</translation>
    </message>
    <message>
        <source>active</source>
        <translation>활성</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>일시 중지됨</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/rad</name>
    <message>
        <source>Queue job types</source>
        <translation>큐 작업 유형</translation>
    </message>
    <message>
        <source>The kinds of job a worker runs (expqueue.ini [JobType_&lt;type&gt;] Handler=, implementing expQueueHandler), the queue's own and every extension's.</source>
        <translation>작업자가 실행하는 작업 종류로, 큐 자체의 작업 유형과 모든 확장의 작업 유형을 포함합니다(expqueue.ini [JobType_&lt;type&gt;] Handler=, expQueueHandler 구현).</translation>
    </message>
    <message>
        <source>Queue job type priority classes</source>
        <translation>큐 작업 유형 우선순위 클래스</translation>
    </message>
    <message>
        <source>The priority class each job type runs in (expqueue.ini [JobType_&lt;type&gt;] Class=, a [QueueClass_&lt;class&gt;] block); refers to the job types, so not added again.</source>
        <translation>각 작업 유형이 실행되는 우선순위 클래스입니다(expqueue.ini [JobType_&lt;type&gt;] Class=, [QueueClass_&lt;class&gt;] 블록). 작업 유형을 참조하므로 다시 추가되지 않습니다.</translation>
    </message>
    <message>
        <source>Queue priority classes</source>
        <translation>큐 우선순위 클래스</translation>
    </message>
    <message>
        <source>The lanes the scheduler shares the workers between (expqueue.ini [QueueSettings] Classes[], each with its [QueueClass_&lt;class&gt;]).</source>
        <translation>스케줄러가 작업자를 나누어 배정하는 레인입니다(expqueue.ini [QueueSettings] Classes[], 각각 해당 [QueueClass_&lt;class&gt;] 포함).</translation>
    </message>
    <message>
        <source>Queue resources</source>
        <translation>큐 리소스</translation>
    </message>
    <message>
        <source>The resources whose concurrency the queue limits (expqueue.ini [QueueResource_&lt;resource&gt;]).</source>
        <translation>큐가 동시 실행 수를 제한하는 리소스입니다(expqueue.ini [QueueResource_&lt;resource&gt;]).</translation>
    </message>
    <message>
        <source>Queue commands</source>
        <translation>큐 명령</translation>
    </message>
    <message>
        <source>The commands ./console ext:expqueue:&lt;name&gt; (expQueueCommand::COMMANDS).</source>
        <translation>./console ext:expqueue:&lt;name&gt; 명령입니다(expQueueCommand::COMMANDS).</translation>
    </message>
</context>
</TS>
