-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 11:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `caravan_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','registrar','finance','leader') NOT NULL DEFAULT 'registrar',
  `assigned_caravan_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `username`, `password`, `role`, `assigned_caravan_id`, `created_at`) VALUES
(1, 'توسعه دهنده', 'admin', '$2y$10$ILL80qS4cjPfIBIBPZfzEeuNFY585d6iurQETid.gBhIydpUTY9wG', 'super_admin', NULL, '2026-09-20 19:35:08');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `badge_type` enum('urgent','info','success') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `badge_type`, `is_active`, `created_at`, `status`) VALUES
(1, 'اطلاعیه مهم پیرامون گذرنامه زائران اربعین', 'کلیه زائران محترم توجه داشته باشند حداقل اعتبار گذرنامه برای ورود به کشور عراق ۶ ماه کامل از تاریخ اعزام می‌باشد.', 'urgent', 1, '2026-09-20 20:17:04', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'آداب زیارت',
  `slug` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `read_time` varchar(20) DEFAULT '۵ دقیقه',
  `content` longtext NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `status` enum('published','draft') DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `category`, `slug`, `summary`, `read_time`, `content`, `image`, `views`, `status`, `created_at`) VALUES
(4, 'راهنمای جامع سفر به عتبات عالیات؛ از آمادگی‌های قبل از حرکت تا آداب تشرف به حرم‌های مطهر', 'راهنمای سفر و هتل‌ها', '', 'هر آنچه یک زائر برای تشرف به نجف اشرف، کربلای معلی، سامرا و کاظمین نیاز دارد؛ شامل چک‌لیست ضروری مدارک و وسایل، بهداشت سفر، احکام و تجربیات طلایی خادمین کاروان.', '۶ دقیقه', '<p>زیارت عتبات مقدسه اهل&zwnj;بیت (علیهم&zwnj;السلام) در کشور عراق، توفیقی عظیم و سفری سرشار از معرفت و معنویت است. برای اینکه زائر محترم بتواند بیشترین بهرهٔ روحی را از این سفر قدسی ببرد و با چالش&zwnj;های اجرایی یا جسمانی مواجه نشود، رعایت برخی نکات پیش از اعزام و در طول اقامت در شهرهای نجف، کربلا، کاظمین و سامرا ضروری است. در این مقاله، تجربیات خادمین کاروان را در قالب یک راهنمای کاربردی گردآوری کرده&zwnj;ایم.</p>\r\n<h2>۱. مدارک شناسایی و اقدامات پیش از اعزام</h2>\r\n<p>پیش از هر اقدامی، از سلامت و اعتبار مدارک خود اطمینان حاصل فرمایید:</p>\r\n<ul>\r\n<li><strong>اعتبار گذرنامه:</strong> طبق قوانین مرزبانی و سازمان حج و زیارت، گذرنامه مسافر از روز اعزام باید <em>حداقل ۶ ماه کامل اعتبار</em> داشته باشد.</li>\r\n<li><strong>کپی مدارک:</strong> پیشنهاد می&zwnj;شود یک نسخه کپی از صفحه اول گذرنامه و کارت ملی را در جیب یا کیفی جداگانه همراه داشته باشید.</li>\r\n<li><strong>بررسی ممنوع&zwnj;الخروجی:</strong> حتماً چند روز قبل از تاریخ حرکت، وضعیت خروج از کشور خود را از طریق سامانه&zwnj;های مربوطه بررسی فرمایید.</li>\r\n<li><strong>تثبیت ثبت&zwnj;نام:</strong> مطمئن شوید که پرونده شما در <a href=\"tracking.php\">بخش پیگیری پرونده کاروان</a> در وضعیت &laquo;تأیید قطعی&raquo; قرار گرفته باشد.</li>\r\n</ul>\r\n<blockquote>&laquo;زیارت امام حسین (ع) قلوب مؤمنین را جلا می&zwnj;دهد و زائر در تمامی گام&zwnj;های این سفر، در پناه عنایت خاصه پروردگار و اهل&zwnj;بیت است.&raquo;</blockquote>\r\n<h2>۲. چک&zwnj;لیست وسایل ضروری و ملزومات کوله&zwnj;پشتی</h2>\r\n<p>سفر زیارتی نیازمند سبکی و چابکی است. بار اضافه تنها باعث خستگی زائر در ترانسفرها و فرودگاه&zwnj;ها خواهد شد. جدول زیر راهنمای مناسبی برای بستن چمدان و ساک دستی شماست:</p>\r\n<table>\r\n<thead>\r\n<tr>\r\n<th>دسته اقلام</th>\r\n<th>وسایل پیشنهادی و ضروری</th>\r\n<th>توضیحات خادم کاروان</th>\r\n</tr>\r\n</thead>\r\n<tbody>\r\n<tr>\r\n<td><strong>پوشاک و کفش</strong></td>\r\n<td>کفش طبی و راحت و بدون بند، دمپایی سبک، لباس&zwnj;های نخی و راحت، جوراب اضافی، چادر سبک برای بانوان.</td>\r\n<td>به دلیل مسافت&zwnj;های پیاده&zwnj;روی تا حرم&zwnj;ها، راحتی پا بسیار حائز اهمیت است.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>بهداشتی و دارویی</strong></td>\r\n<td>داروهای تخصصی به همراه نسخه پزشک، ماسک بهداشتی، کرم ضدسوختگی، ژل ضدعفونی&zwnj;کننده، صابون کوچک و مسواک.</td>\r\n<td>همراه داشتن داروهای دارای ترکیبات مخدر یا کدئین&zwnj;دار در گمرک عراق اکیداً ممنوع است.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>الکترونیک و ارتباطی</strong></td>\r\n<td>پاوربانک با ظرفیت استاندارد، شارژر موبایل، هندزفری جهت استماع ادعیه و زیارات، سه راهی کوچک در صورت نیاز.</td>\r\n<td>سیم&zwnj;کارت&zwnj;های عراقی (آسیاسل، زین، کورک) معمولاً توسط خادمین در مرز یا هتل راهنمایی و فعال می&zwnj;شوند.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<h2>۳. آداب معنوی تشرف به مشاهد مشرفه</h2>\r\n<p>حضور در حرم&zwnj;های مطهر نیازمند آمادگی قلبی است. بزرگان دین و علما همواره بر رعایت آداب زیر تأکید داشته&zwnj;اند:</p>\r\n<ol>\r\n<li><strong>غسل زیارت و طهارت باطن:</strong> پیش از خروج از هتل و حرکت به سمت حرم مطهر امیرالمؤمنین (ع) یا حرم حضرت سیدالشهدا (ع)، غسل زیارت و وضو گرفتن سفارش شده است.</li>\r\n<li><strong>اذن دخول:</strong> هنگام ورود به صحن و رواق&zwnj;ها، با طمأنینه و وقار بایستید، اذن دخول بخوانید و در صورت ایجاد رقّت قلب وارد شوید.</li>\r\n<li><strong>رعایت حال سالمندان و بیماران:</strong> در هنگام بوسیدن ضریح مطهر و زیارت، از ایجاد فشار و ازدحام خودداری کرده و آرامش سایر زائرین را پاس بدارید.</li>\r\n<li><strong>استفاده از محافل جمعی کاروان:</strong> برنامه&zwnj;های زیارت جمعی، روضه&zwnj;خوانی و جلسات معرفتی که توسط روحانی و مداح کاروان در هتل یا حرم برگزار می&zwnj;شود، فرصتی طلایی برای ارتقای روحی سفر است.</li>\r\n</ol>\r\n<h2>۴. نکات طلایی تغذیه و سلامت در عراق</h2>\r\n<p>تغییر آب&zwnj;وهوا و اقلیم غذایی ممکن است باعث بروز کسالت&zwnj;های گوارشی یا سرماخوردگی شود. برای حفظ شادابی خود در طول سفر به این موارد توجه نمایید:</p>\r\n<ul>\r\n<li>تا حد امکان از آب&zwnj;های معدنی بسته&zwnj;بندی&zwnj;شده و پلمپ استفاده کنید.</li>\r\n<li>در هتل&zwnj;ها تمامی وعده&zwnj;های غذایی با نظارت بهداشتی و طبع ایرانی طبخ می&zwnj;شوند، لذا از مصرف غذاهای فاقد بهداشت در معابر باز پرهیز فرمایید.</li>\r\n<li>نوشیدن مایعات گرم و استراحت کافی میان ساعات زیارت را فراموش نکنید.</li>\r\n</ul>\r\n<hr>\r\n<p><strong>سخن پایانی:</strong> خادمین شما در مجموعه زیارتی، تمام تلاش خود را به کار بسته&zwnj;اند تا با برنامه&zwnj;ریزی منظم، هتل&zwnj;های نزدیک و حمل&zwnj;ونقل امن، سفری خاطره&zwnj;انگیز را برای شما رقم بزنند. در صورت داشتن هرگونه سوال یا نیاز به راهنمایی در طول سفر، همواره می&zwnj;توانید با سرپرست کاروان تماس حاصل فرمایید.</p>\r\n<p style=\"text-align: center;\"><em>التماس دعا در جوار بارگاه ملکوتی حضرت اباعبدالله الحسین (ع) و اباالفضل العباس (ع)</em></p>', 'blog_1789993879_7fb3c437.jpg', 5, 'published', '2026-09-21 12:31:19');

-- --------------------------------------------------------

--
-- Table structure for table `caravans`
--

CREATE TABLE `caravans` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `itinerary` text DEFAULT NULL,
  `stay_najaf` varchar(150) DEFAULT NULL,
  `stay_karbala` varchar(150) DEFAULT NULL,
  `stay_samarra` varchar(150) DEFAULT NULL,
  `special_pilgrimages` text DEFAULT NULL,
  `trip_type` enum('هوایی','زمینی') NOT NULL,
  `transport_departure` varchar(100) DEFAULT 'اتوبوس VIP ۲۵ نفره',
  `transport_return` varchar(100) DEFAULT 'اتوبوس VIP ۲۵ نفره',
  `transport_internal` varchar(100) DEFAULT 'اتوبوس توریستی عراقی (کولردار)',
  `departure_place` varchar(150) DEFAULT NULL,
  `departure_time` varchar(20) DEFAULT NULL,
  `requires_passport` tinyint(1) DEFAULT 1,
  `start_date` varchar(50) NOT NULL,
  `end_date` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `total_capacity` int(11) NOT NULL,
  `remaining_capacity` int(11) NOT NULL,
  `price_adult` int(11) NOT NULL,
  `price_child` int(11) NOT NULL,
  `price_infant` int(11) NOT NULL,
  `deposit_amount` int(11) DEFAULT 0,
  `food_services` varchar(150) DEFAULT 'صبحانه، ناهار، شام به همراه میوه و دسر',
  `leader_name` varchar(100) DEFAULT NULL,
  `cleric_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rules_text` text DEFAULT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `caravan_reports`
--

CREATE TABLE `caravan_reports` (
  `id` int(11) NOT NULL,
  `caravan_id` int(11) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `report_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pilgrims`
--

CREATE TABLE `pilgrims` (
  `id` int(11) NOT NULL,
  `caravan_id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `national_code` varchar(10) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT 'male',
  `age_group` enum('adult','child','infant') DEFAULT 'adult',
  `birth_date` varchar(20) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` varchar(20) DEFAULT NULL,
  `national_card_image` varchar(255) DEFAULT NULL,
  `passport_image` varchar(255) DEFAULT NULL,
  `payment_receipt_image` varchar(255) DEFAULT NULL,
  `final_price` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `payment_ref` varchar(100) DEFAULT NULL,
  `status` enum('pending','verified','rejected','cancelled') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `rules_accepted` tinyint(1) DEFAULT 1,
  `registered_by` varchar(50) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('address', 'آدرس دفتر'),
('bale_channel', 'لینک کانال بله'),
('bank_card_number', '6037-0000-0000-0000'),
('bank_name', 'نام بانک'),
('bank_owner_name', 'نام صاحب حساب'),
('bank_shaba_number', 'شماره شبا'),
('card_number', '6037-9918-0000-0000 به نام مجموعه فرهنگی'),
('default_rules', '۱. رعایت شئونات اسلامی و اخلاقی در طول سفر الزامی است.\r\n۲. به همراه داشتن هرگونه داروی ممنوعه در کشور مقصد پیگرد قانونی دارد.\r\n۳. حفظ وسایل شخصی و گذرنامه بر عهده زائر محترم می‌باشد.\r\n۴. در صورت انصراف، عودت وجه تابع قوانین کنسلی مجموعه خواهد بود.'),
('eitaa_channel', 'لینک کانال ایتا'),
('eitaa_id', 'id ایتا'),
('faqs_list', '[{\"question\":\"گذرنامه باید چه میزان اعتبار داشته باشد؟\",\"answer\":\"برای سفرهای عتبات عالیات (عراق)، گذرنامه از تاریخ روز اعزام باید حداقل ۶ ماه کامل اعتبار داشته باشد.\"},{\"question\":\"شرایط پرداخت بیعانه و تسویه‌حساب به چه صورت است؟\",\"answer\":\"جهت تثبیت صندلی، واریز مبلغ بیعانه اعلام‌شده در فرم الزامی است. مابقی هزینه سفر حداکثر تا ۷۲ ساعت قبل از اعزام باید تسویه گردد.\"},{\"question\":\"آیا ارز مسافرتی به زائران این کاروان تعلق می‌گیرد؟\",\"answer\":\"بله، پس از ثبت‌نام نهایی و صدور مانیفست رسمی، اطلاعات زائران ثبت شده و امکان دریافت ارز مسافرتی بانکی فراهم می‌گردد.\"}]'),
('instagram_page', 'صفحه اینستاگرام'),
('mobile_number', '09111111111'),
('phone_number', '09111111111'),
('rules_and_conditions', ''),
('site_description', ''),
('site_logo', 'logo_1789992703.jpg'),
('site_tagline', 'مجری تخصصی تورهای عتبات عالیات و مشهد مقدس'),
('site_title', 'مجموعه فرهنگی زیارتی '),
('sms_api_key', ''),
('sms_password', ''),
('sms_provider', 'none'),
('sms_sender_line', ''),
('sms_username', ''),
('telegram_channel', 'لینک کانال تلگرام');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `receiver_phone` varchar(20) NOT NULL,
  `receiver_name` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `status` enum('sent','failed','logged') DEFAULT 'sent',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `caravans`
--
ALTER TABLE `caravans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `caravan_reports`
--
ALTER TABLE `caravan_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caravan_id` (`caravan_id`),
  ADD KEY `leader_id` (`leader_id`);

--
-- Indexes for table `pilgrims`
--
ALTER TABLE `pilgrims`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `caravans`
--
ALTER TABLE `caravans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `caravan_reports`
--
ALTER TABLE `caravan_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pilgrims`
--
ALTER TABLE `pilgrims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `caravan_reports`
--
ALTER TABLE `caravan_reports`
  ADD CONSTRAINT `caravan_reports_ibfk_1` FOREIGN KEY (`caravan_id`) REFERENCES `caravans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `caravan_reports_ibfk_2` FOREIGN KEY (`leader_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
