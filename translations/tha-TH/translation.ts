<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0">
<context>
    <name>design/admin/parts/setup/menu</name>
    <message>
        <source>Job queue</source>
        <translation>คิวงาน</translation>
    </message>
</context>
<context>
    <name>extension/expqueue</name>
    <message>
        <source>Job queue</source>
        <translation>คิวงาน</translation>
    </message>
    <message>
        <source>The tables of the job queue are missing in this database. Run ./console ext:expqueue:install-schema on the server.</source>
        <translation>ไม่มีตารางของคิวงานในฐานข้อมูลนี้ เรียกใช้ ./console ext:expqueue:install-schema บนเซิร์ฟเวอร์</translation>
    </message>
    <message>
        <source>Dashboard</source>
        <translation>แดชบอร์ด</translation>
    </message>
    <message>
        <source>The controls need the extension ezformtoken.</source>
        <translation>ตัวควบคุมต้องใช้ส่วนขยาย ezformtoken</translation>
    </message>
    <message>
        <source>the queue</source>
        <translation>คิว</translation>
    </message>
    <message>
        <source>Paused %what.</source>
        <translation>หยุดชั่วคราว%what แล้ว</translation>
    </message>
    <message>
        <source>Resumed %what.</source>
        <translation>ดำเนินการต่อ%what แล้ว</translation>
    </message>
    <message>
        <source>Nothing changed.</source>
        <translation>ไม่มีการเปลี่ยนแปลง</translation>
    </message>
    <message>
        <source>The job had already ended.</source>
        <translation>งานสิ้นสุดไปแล้ว</translation>
    </message>
    <message>
        <source>Cancel requested: the job stops at its next check.</source>
        <translation>ส่งคำขอยกเลิกแล้ว: งานจะหยุดในการตรวจสอบครั้งถัดไป</translation>
    </message>
    <message>
        <source>The job is cancelled.</source>
        <translation>ยกเลิกงานแล้ว</translation>
    </message>
    <message>
        <source>The job is back in the queue.</source>
        <translation>งานกลับเข้าคิวแล้ว</translation>
    </message>
    <message>
        <source>The job cannot be requeued.</source>
        <translation>ไม่สามารถนำงานกลับเข้าคิวได้</translation>
    </message>
    <message>
        <source>%count dead job(s) are back in the queue.</source>
        <translation>งานที่ตายแล้ว %count งานกลับเข้าคิวแล้ว</translation>
    </message>
    <message>
        <source>The group is cancelled (%count job(s)).</source>
        <translation>ยกเลิกกลุ่มแล้ว (%count งาน)</translation>
    </message>
    <message>
        <source>A test job is queued; a worker (cron within a minute, or the service) runs it.</source>
        <translation>เพิ่มงานทดสอบเข้าคิวแล้ว ตัวทำงาน (cron ภายในหนึ่งนาที หรือบริการ) จะเรียกใช้งาน</translation>
    </message>
    <message>
        <source>Unknown action.</source>
        <translation>ไม่รู้จักการดำเนินการ</translation>
    </message>
    <message>
        <source>Group</source>
        <translation>กลุ่ม</translation>
    </message>
    <message>
        <source>Jobs</source>
        <translation>งาน</translation>
    </message>
    <message>
        <source>Resume queue</source>
        <translation>ดำเนินการต่อคิว</translation>
    </message>
    <message>
        <source>Pause queue</source>
        <translation>หยุดคิวชั่วคราว</translation>
    </message>
    <message>
        <source>Pause the whole queue? Running jobs finish; no new job starts.</source>
        <translation>หยุดคิวทั้งหมดชั่วคราวหรือไม่? งานที่กำลังทำงานจะเสร็จสิ้น แต่จะไม่เริ่มงานใหม่</translation>
    </message>
    <message>
        <source>Send a test job</source>
        <translation>ส่งงานทดสอบ</translation>
    </message>
    <message>
        <source>The queue is paused by the setting expqueue.ini [QueueSettings] Paused=enabled.</source>
        <translation>คิวถูกหยุดชั่วคราวโดยการตั้งค่า expqueue.ini [QueueSettings] Paused=enabled</translation>
    </message>
    <message>
        <source>Key figures</source>
        <translation>ตัวเลขสำคัญ</translation>
    </message>
    <message>
        <source>Ready</source>
        <translation>พร้อม</translation>
    </message>
    <message>
        <source>Running</source>
        <translation>กำลังทำงาน</translation>
    </message>
    <message>
        <source>Waiting or retrying</source>
        <translation>กำลังรอหรือลองใหม่</translation>
    </message>
    <message>
        <source>Dead letter</source>
        <translation>งานที่ล้มเหลวถาวร</translation>
    </message>
    <message>
        <source>Done in the last hour</source>
        <translation>เสร็จในชั่วโมงที่ผ่านมา</translation>
    </message>
    <message>
        <source>Oldest ready job</source>
        <translation>งานที่พร้อมเก่าที่สุด</translation>
    </message>
    <message>
        <source>Workers alive</source>
        <translation>ตัวทำงานที่ยังทำงานอยู่</translation>
    </message>
    <message>
        <source>Jobs ended per hour</source>
        <translation>งานที่สิ้นสุดต่อชั่วโมง</translation>
    </message>
    <message>
        <source>last 24 hours: %done done, %dead dead</source>
        <translation>24 ชั่วโมงที่ผ่านมา: เสร็จ %done ตาย %dead</translation>
    </message>
    <message>
        <source>No job ended in the last 24 hours.</source>
        <translation>ไม่มีงานที่สิ้นสุดใน 24 ชั่วโมงที่ผ่านมา</translation>
    </message>
    <message>
        <source>Jobs ended per hour over the last 24 hours; the table below has the numbers.</source>
        <translation>งานที่สิ้นสุดต่อชั่วโมงใน 24 ชั่วโมงที่ผ่านมา ตารางด้านล่างมีตัวเลข</translation>
    </message>
    <message>
        <source>%n done</source>
        <translation>เสร็จ %n</translation>
    </message>
    <message>
        <source>%n dead</source>
        <translation>ตาย %n</translation>
    </message>
    <message>
        <source>peak %n per hour</source>
        <translation>สูงสุด %n ต่อชั่วโมง</translation>
    </message>
    <message>
        <source>Show as a table</source>
        <translation>แสดงเป็นตาราง</translation>
    </message>
    <message>
        <source>Hour</source>
        <translation>ชั่วโมง</translation>
    </message>
    <message>
        <source>Priority classes</source>
        <translation>คลาสลำดับความสำคัญ</translation>
    </message>
    <message>
        <source>Class</source>
        <translation>คลาส</translation>
    </message>
    <message>
        <source>Weight</source>
        <translation>น้ำหนัก</translation>
    </message>
    <message>
        <source>Waiting</source>
        <translation>กำลังรอ</translation>
    </message>
    <message>
        <source>Dead</source>
        <translation>ตาย</translation>
    </message>
    <message>
        <source>Oldest</source>
        <translation>เก่าที่สุด</translation>
    </message>
    <message>
        <source>p95 wait (1 h)</source>
        <translation>เวลารอ p95 (1 ชม.)</translation>
    </message>
    <message>
        <source>Done (1 h)</source>
        <translation>เสร็จ (1 ชม.)</translation>
    </message>
    <message>
        <source>Controls</source>
        <translation>ตัวควบคุม</translation>
    </message>
    <message>
        <source>reserved</source>
        <translation>จองไว้</translation>
    </message>
    <message>
        <source>Resume</source>
        <translation>ดำเนินการต่อ</translation>
    </message>
    <message>
        <source>Pause</source>
        <translation>หยุดชั่วคราว</translation>
    </message>
    <message>
        <source>Resources</source>
        <translation>ทรัพยากร</translation>
    </message>
    <message>
        <source>No job names a resource yet.</source>
        <translation>ยังไม่มีงานที่ระบุทรัพยากร</translation>
    </message>
    <message>
        <source>Resource</source>
        <translation>ทรัพยากร</translation>
    </message>
    <message>
        <source>Running / limit</source>
        <translation>กำลังทำงาน / ขีดจำกัด</translation>
    </message>
    <message>
        <source>State</source>
        <translation>สถานะ</translation>
    </message>
    <message>
        <source>max %n</source>
        <translation>สูงสุด %n</translation>
    </message>
    <message>
        <source>cooldown %t</source>
        <translation>พักการทำงาน %t</translation>
    </message>
    <message>
        <source>last %s</source>
        <translation>ล่าสุด %s</translation>
    </message>
    <message>
        <source>ok</source>
        <translation>ปกติ</translation>
    </message>
    <message>
        <source>Workers</source>
        <translation>เวิร์กเกอร์</translation>
    </message>
    <message>
        <source>Cron part expqueue</source>
        <translation>ส่วน cron expqueue</translation>
    </message>
    <message>
        <source>ran %t ago</source>
        <translation>ทำงานเมื่อ %t ที่แล้ว</translation>
    </message>
    <message>
        <source>late: last run %t ago</source>
        <translation>ล่าช้า: ทำงานล่าสุดเมื่อ %t ที่แล้ว</translation>
    </message>
    <message>
        <source>never ran</source>
        <translation>ไม่เคยทำงาน</translation>
    </message>
    <message>
        <source>add php runcronjobs.php -q expqueue to the crontab (every minute)</source>
        <translation>เพิ่ม php runcronjobs.php -q expqueue ใน crontab (ทุกนาที)</translation>
    </message>
    <message>
        <source>systemd service</source>
        <translation>บริการ systemd</translation>
    </message>
    <message>
        <source>installed</source>
        <translation>ติดตั้งแล้ว</translation>
    </message>
    <message>
        <source>%n worker(s) alive</source>
        <translation>ตัวทำงานที่ยังทำงานอยู่ %n ตัว</translation>
    </message>
    <message>
        <source>not installed: cron is the worker</source>
        <translation>ไม่ได้ติดตั้ง: cron เป็นตัวทำงาน</translation>
    </message>
    <message>
        <source>Worker</source>
        <translation>ตัวทำงาน</translation>
    </message>
    <message>
        <source>Started by</source>
        <translation>เริ่มโดย</translation>
    </message>
    <message>
        <source>Up</source>
        <translation>ขึ้น</translation>
    </message>
    <message>
        <source>Heartbeat</source>
        <translation>สัญญาณชีพ</translation>
    </message>
    <message>
        <source>Done</source>
        <translation>เสร็จสิ้น</translation>
    </message>
    <message>
        <source>job %id</source>
        <translation>งาน %id</translation>
    </message>
    <message>
        <source>No worker is running right now; the next cron run starts one when jobs are ready.</source>
        <translation>ขณะนี้ไม่มีตัวทำงานที่กำลังทำงาน การเรียกใช้ cron ครั้งถัดไปจะเริ่มหนึ่งตัวเมื่องานพร้อม</translation>
    </message>
    <message>
        <source>Running now</source>
        <translation>กำลังทำงานอยู่</translation>
    </message>
    <message>
        <source>Nothing runs.</source>
        <translation>ไม่มีงานที่กำลังทำงาน</translation>
    </message>
    <message>
        <source>all %n</source>
        <translation>ทั้งหมด %n</translation>
    </message>
    <message>
        <source>Requeue all</source>
        <translation>นำกลับเข้าคิวทั้งหมด</translation>
    </message>
    <message>
        <source>Put every dead job back into the queue with its attempts reset?</source>
        <translation>นำทุกงานที่ตายกลับเข้าคิวพร้อมรีเซ็ตจำนวนความพยายามหรือไม่?</translation>
    </message>
    <message>
        <source>Empty: no job has failed for good.</source>
        <translation>ว่าง: ไม่มีงานที่ล้มเหลวถาวร</translation>
    </message>
    <message>
        <source>Groups</source>
        <translation>กลุ่ม</translation>
    </message>
    <message>
        <source>No job group yet.</source>
        <translation>ยังไม่มีกลุ่มงาน</translation>
    </message>
    <message>
        <source>Cancel group</source>
        <translation>ยกเลิกกลุ่ม</translation>
    </message>
    <message>
        <source>Cancel every job of this group that has not ended?</source>
        <translation>ยกเลิกทุกงานของกลุ่มนี้ที่ยังไม่สิ้นสุดหรือไม่?</translation>
    </message>
    <message>
        <source>Progress</source>
        <translation>ความคืบหน้า</translation>
    </message>
    <message>
        <source>%done of %total ended (%percent%)</source>
        <translation>สิ้นสุด %done จาก %total (%percent%)</translation>
    </message>
    <message>
        <source>Id</source>
        <translation>ID</translation>
    </message>
    <message>
        <source>Type</source>
        <translation>ประเภท</translation>
    </message>
    <message>
        <source>Created</source>
        <translation>สร้างเมื่อ</translation>
    </message>
    <message>
        <source>The group has no job.</source>
        <translation>กลุ่มไม่มีงาน</translation>
    </message>
    <message>
        <source>Newer</source>
        <translation>ใหม่กว่า</translation>
    </message>
    <message>
        <source>Older</source>
        <translation>เก่ากว่า</translation>
    </message>
    <message>
        <source>Cancel</source>
        <translation>ยกเลิก</translation>
    </message>
    <message>
        <source>Cancel this job?</source>
        <translation>ยกเลิกงานนี้หรือไม่?</translation>
    </message>
    <message>
        <source>Requeue</source>
        <translation>นำกลับเข้าคิว</translation>
    </message>
    <message>
        <source>Job</source>
        <translation>งาน</translation>
    </message>
    <message>
        <source>priority %p</source>
        <translation>ลำดับความสำคัญ %p</translation>
    </message>
    <message>
        <source>Attempts</source>
        <translation>ความพยายาม</translation>
    </message>
    <message>
        <source>timeout %t</source>
        <translation>หมดเวลา %t</translation>
    </message>
    <message>
        <source>Queued</source>
        <translation>อยู่ในคิว</translation>
    </message>
    <message>
        <source>%t ago</source>
        <translation>%t ที่แล้ว</translation>
    </message>
    <message>
        <source>Started</source>
        <translation>เริ่มเมื่อ</translation>
    </message>
    <message>
        <source>waited %w, ran %r</source>
        <translation>รอ %w ทำงาน %r</translation>
    </message>
    <message>
        <source>Ended</source>
        <translation>สิ้นสุดเมื่อ</translation>
    </message>
    <message>
        <source>Next attempt</source>
        <translation>ความพยายามครั้งถัดไป</translation>
    </message>
    <message>
        <source>in %t</source>
        <translation>ใน %t</translation>
    </message>
    <message>
        <source>Lease</source>
        <translation>สัญญาเช่า</translation>
    </message>
    <message>
        <source>%t left</source>
        <translation>เหลือ %t</translation>
    </message>
    <message>
        <source>Queued by</source>
        <translation>เข้าคิวโดย</translation>
    </message>
    <message>
        <source>Idempotency key</source>
        <translation>คีย์ idempotency</translation>
    </message>
    <message>
        <source>After</source>
        <translation>หลัง</translation>
    </message>
    <message>
        <source>Lane</source>
        <translation>เลน</translation>
    </message>
    <message>
        <source>Last error</source>
        <translation>ข้อผิดพลาดล่าสุด</translation>
    </message>
    <message>
        <source>Parameters</source>
        <translation>พารามิเตอร์</translation>
    </message>
    <message>
        <source>None.</source>
        <translation>ไม่มี.</translation>
    </message>
    <message>
        <source>Result</source>
        <translation>ผลลัพธ์</translation>
    </message>
    <message>
        <source>checkpoint saved (%n bytes)</source>
        <translation>บันทึกจุดตรวจแล้ว (%n ไบต์)</translation>
    </message>
    <message>
        <source>None yet.</source>
        <translation>ยังไม่มี</translation>
    </message>
    <message>
        <source>History</source>
        <translation>ประวัติ</translation>
    </message>
    <message>
        <source>%n event(s)</source>
        <translation>%n เหตุการณ์</translation>
    </message>
    <message>
        <source>Time</source>
        <translation>เวลา</translation>
    </message>
    <message>
        <source>Event</source>
        <translation>เหตุการณ์</translation>
    </message>
    <message>
        <source>Attempt</source>
        <translation>ความพยายาม</translation>
    </message>
    <message>
        <source>Message</source>
        <translation>ข้อความ</translation>
    </message>
    <message>
        <source>all</source>
        <translation>ทั้งหมด</translation>
    </message>
    <message>
        <source>Also</source>
        <translation>รวมถึง</translation>
    </message>
    <message>
        <source>type %t ×</source>
        <translation>ชนิด %t ×</translation>
    </message>
    <message>
        <source>resource %r ×</source>
        <translation>ทรัพยากร %r ×</translation>
    </message>
    <message>
        <source>group %g</source>
        <translation>กลุ่ม %g</translation>
    </message>
    <message>
        <source>Requeue all dead jobs</source>
        <translation>นำงานที่ตายทั้งหมดกลับเข้าคิว</translation>
    </message>
    <message>
        <source>No job matches.</source>
        <translation>ไม่มีงานที่ตรงกัน</translation>
    </message>
    <message>
        <source>%from to %to</source>
        <translation>%from ถึง %to</translation>
    </message>
    <message>
        <source>Priority</source>
        <translation>ลำดับความสำคัญ</translation>
    </message>
    <message>
        <source>Age</source>
        <translation>อายุ</translation>
    </message>
    <message>
        <source>cancel requested</source>
        <translation>ส่งคำขอยกเลิกแล้ว</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/state</name>
    <message>
        <source>done</source>
        <translation>เสร็จแล้ว</translation>
    </message>
    <message>
        <source>dead</source>
        <translation>ตาย</translation>
    </message>
    <message>
        <source>queued</source>
        <translation>อยู่ในคิว</translation>
    </message>
    <message>
        <source>ready</source>
        <translation>พร้อม</translation>
    </message>
    <message>
        <source>running</source>
        <translation>กำลังทำงาน</translation>
    </message>
    <message>
        <source>retry</source>
        <translation>ลองใหม่</translation>
    </message>
    <message>
        <source>held</source>
        <translation>ถูกระงับ</translation>
    </message>
    <message>
        <source>cancelled</source>
        <translation>ยกเลิกแล้ว</translation>
    </message>
    <message>
        <source>active</source>
        <translation>ใช้งานอยู่</translation>
    </message>
    <message>
        <source>paused</source>
        <translation>หยุดชั่วคราว</translation>
    </message>
</context>
<context>
    <name>extension/expqueue/rad</name>
    <message>
        <source>Queue job types</source>
        <translation>ชนิดงานของคิว</translation>
    </message>
    <message>
        <source>The kinds of job a worker runs (expqueue.ini [JobType_&lt;type&gt;] Handler=, implementing expQueueHandler), the queue&apos;s own and every extension&apos;s.</source>
        <translation>ประเภทของงานที่ตัวทำงานเรียกใช้ (expqueue.ini [JobType_&lt;type&gt;] Handler= ที่ implements expQueueHandler) ของคิวเองและของทุกส่วนขยาย</translation>
    </message>
    <message>
        <source>Queue job type priority classes</source>
        <translation>คลาสลำดับความสำคัญของชนิดงานของคิว</translation>
    </message>
    <message>
        <source>The priority class each job type runs in (expqueue.ini [JobType_&lt;type&gt;] Class=, a [QueueClass_&lt;class&gt;] block); refers to the job types, so not added again.</source>
        <translation>คลาสลำดับความสำคัญที่แต่ละชนิดงานทำงานอยู่ (expqueue.ini [JobType_&lt;type&gt;] Class= บล็อก [QueueClass_&lt;class&gt;]) อ้างถึงชนิดงาน จึงไม่เพิ่มซ้ำ</translation>
    </message>
    <message>
        <source>Queue priority classes</source>
        <translation>คลาสลำดับความสำคัญของคิว</translation>
    </message>
    <message>
        <source>The lanes the scheduler shares the workers between (expqueue.ini [QueueSettings] Classes[], each with its [QueueClass_&lt;class&gt;]).</source>
        <translation>เลนที่ตัวจัดตารางแบ่งปันตัวทำงาน (expqueue.ini [QueueSettings] Classes[] แต่ละเลนมี [QueueClass_&lt;class&gt;] ของตัวเอง)</translation>
    </message>
    <message>
        <source>Queue resources</source>
        <translation>ทรัพยากรของคิว</translation>
    </message>
    <message>
        <source>The resources whose concurrency the queue limits (expqueue.ini [QueueResource_&lt;resource&gt;]).</source>
        <translation>ทรัพยากรที่คิวจำกัดการทำงานพร้อมกัน (expqueue.ini [QueueResource_&lt;resource&gt;])</translation>
    </message>
    <message>
        <source>Queue commands</source>
        <translation>คำสั่งของคิว</translation>
    </message>
    <message>
        <source>The commands ./console ext:expqueue:&lt;name&gt; (expQueueCommand::COMMANDS).</source>
        <translation>คำสั่ง ./console ext:expqueue:&lt;name&gt; (expQueueCommand::COMMANDS)</translation>
    </message>
</context>
</TS>
