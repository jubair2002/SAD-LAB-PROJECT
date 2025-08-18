<?php
// Start session for language preference

require_once 'config.php';
require_once 'dashboard_base.php';
checkAccess('regular');

// Get user details
$user = getUserDetails($conn, $_SESSION['user_id']);
// Set default language to English if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

// Handle language switch
if (isset($_GET['language'])) {
    $_SESSION['language'] = ($_GET['language'] == 'bn') ? 'bn' : 'en';
    header('Location: ' . str_replace('?language='.$_GET['language'], '', $_SERVER['REQUEST_URI']));
    exit();
}

// Disaster data in both languages
$disasterData = [
    'flood' => [
        'en' => [
            'title' => 'Flood',
            'dos' => [
                'Move to higher ground immediately',
                'Turn off utilities at main switches',
                'Listen to emergency broadcasts',
                'Avoid walking through moving water',
                'Use a stick to check water depth'
            ],
            'donts' => [
                "Don't drive through flooded areas",
                "Don't touch electrical equipment if wet",
                "Don't walk through flood waters",
                "Don't ignore evacuation orders",
                "Don't return home until authorities say it's safe"
            ]
        ],
        'bn' => [
            'title' => 'বন্যা',
            'dos' => [
                'অবিলম্বে উঁচু স্থানে যান',
                'মূল সুইচ থেকে ইউটিলিটি বন্ধ করুন',
                'জরুরি সম্প্রচার শুনুন',
                'চলমান পানিতে হাঁটা এড়িয়ে চলুন',
                'পানির গভীরতা পরীক্ষা করতে লাঠি ব্যবহার করুন'
            ],
            'donts' => [
                'প্লাবিত এলাকায় গাড়ি চালাবেন না',
                'ভিজে থাকলে বৈদ্যুতিক সরঞ্জাম স্পর্শ করবেন না',
                'বন্যার পানিতে হাঁটবেন না',
                'খালাসের আদেশ উপেক্ষা করবেন না',
                'কর্তৃপক্ষ নিরাপদ বলে না দেওয়া পর্যন্ত বাড়ি ফিরবেন না'
            ]
        ]
    ],
    'fire' => [
        'en' => [
            'title' => 'Fire',
            'dos' => [
                'Stay low to avoid smoke inhalation',
                'Check doors for heat before opening',
                'Use fire extinguisher for small fires',
                'Know your emergency exits',
                'Stop, drop, and roll if clothes catch fire'
            ],
            'donts' => [
                "Don't use elevators during fire",
                "Don't re-enter a burning building",
                "Don't open hot doors",
                "Don't hide during fire",
                "Don't panic, move calmly"
            ]
        ],
        'bn' => [
            'title' => 'অগ্নিকাণ্ড',
            'dos' => [
                'ধোঁয়া এড়াতে নিচু হয়ে থাকুন',
                'খোলার আগে দরজা গরম কিনা পরীক্ষা করুন',
                'ছোট আগুনের জন্য ফায়ার এক্সটিংগুইশার ব্যবহার করুন',
                'জরুরি প্রস্থান পথ জানুন',
                'পোশাকে আগুন লাগলে থামুন, পড়ুন এবং গড়াগড়ি দিন'
            ],
            'donts' => [
                'আগুনের সময় লিফট ব্যবহার করবেন না',
                'জ্বলন্ত বিল্ডিংয়ে পুনরায় প্রবেশ করবেন না',
                'গরম দরজা খুলবেন না',
                'আগুনের সময় লুকাবেন না',
                'আতঙ্কিত হবেন না, শান্তভাবে সরুন'
            ]
        ]
    ],
    'earthquake' => [
        'en' => [
            'title' => 'Earthquake',
            'dos' => [
                'Drop, cover, and hold on',
                'Stay indoors until shaking stops',
                'Stay away from windows',
                'Use stairs, not elevators',
                'Be prepared for aftershocks'
            ],
            'donts' => [
                "Don't run outside during shaking",
                "Don't stand in doorways (modern advice)",
                "Don't use elevators",
                "Don't panic and rush to exits",
                "Don't light matches after earthquake"
            ]
        ],
        'bn' => [
            'title' => 'ভূমিকম্প',
            'dos' => [
                'নিচে নামুন, ঢাকুন এবং ধরে রাখুন',
                'কম্পন বন্ধ না হওয়া পর্যন্ত ঘরের ভিতরে থাকুন',
                'জানালা থেকে দূরে থাকুন',
                'লিফট নয়, সিঁড়ি ব্যবহার করুন',
                'আফটারশকের জন্য প্রস্তুত থাকুন'
            ],
            'donts' => [
                'কম্পনের সময় বাইরে দৌড়াবেন না',
                'দরজায় দাঁড়াবেন না (আধুনিক পরামর্শ)',
                'লিফট ব্যবহার করবেন না',
                'আতঙ্কিত হয়ে প্রস্থানের দিকে ছুটবেন না',
                'ভূমিকম্পের পর দেশলাই জ্বালাবেন না'
            ]
        ]
    ],
    'cyclone' => [
        'en' => [
            'title' => 'Cyclone',
            'dos' => [
                'Stay indoors in strongest part of building',
                'Listen to weather updates',
                'Secure outdoor objects',
                'Turn off utilities if instructed',
                'Have emergency kit ready'
            ],
            'donts' => [
                "Don't go outside during cyclone",
                "Don't ignore evacuation orders",
                "Don't stand near windows",
                "Don't use electrical equipment",
                "Don't venture out immediately after cyclone passes"
            ]
        ],
        'bn' => [
            'title' => 'ঘূর্ণিঝড়',
            'dos' => [
                'বিল্ডিংয়ের সবচেয়ে শক্তিশালী অংশে ঘরের ভিতরে থাকুন',
                'আবহাওয়ার আপডেট শুনুন',
                'বাইরের বস্তু সুরক্ষিত করুন',
                'নির্দেশিত হলে ইউটিলিটি বন্ধ করুন',
                'জরুরি কিট প্রস্তুত রাখুন'
            ],
            'donts' => [
                'ঘূর্ণিঝড়ের সময় বাইরে যাবেন না',
                'খালাসের আদেশ উপেক্ষা করবেন না',
                'জানালার কাছে দাঁড়াবেন না',
                'বৈদ্যুতিক সরঞ্জাম ব্যবহার করবেন না',
                'ঘূর্ণিঝড় কেটে যাওয়ার পরই বের হবেন না'
            ]
        ]
    ]
];

// Get current language
$lang = $_SESSION['language'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($lang == 'en') ? 'Disaster Do\'s and Don\'ts' : 'দুর্যোগের করণীয় ও বর্জনীয়'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #333;
            --light-color: #fff;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: <?php echo ($lang == 'en') ? "'Roboto', sans-serif" : "'Hind Siliguri', sans-serif"; ?>;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--secondary-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 15px 0;
            box-shadow: var(--shadow);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .language-switcher {
            display: flex;
            gap: 10px;
        }
        
        .language-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--light-color);
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        
        .language-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .language-btn.active {
            background: var(--light-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .disaster-nav {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .disaster-btn {
            background: var(--light-color);
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .disaster-btn:hover, .disaster-btn.active {
            background: var(--primary-color);
            color: var(--light-color);
            border-color: var(--primary-color);
        }
        
        .content-section {
            display: none;
            animation: fadeIn 0.5s;
        }
        
        .content-section.active {
            display: block;
        }
        
        .dos-donts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dos, .donts {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .dos h3 {
            color: #2ecc71;
            margin-top: 0;
        }
        
        .donts h3 {
            color: var(--primary-color);
            margin-top: 0;
        }
        
        ul {
            padding-left: 20px;
        }
        
        li {
            margin-bottom: 8px;
        }
        
        .donts li {
            position: relative;
        }
        
        .donts li:before {
            content: '';
            color: var(--primary-color);
            position: absolute;
            left: -20px;
        }
        
        .dos li:before {
            content: '✓';
            color: #2ecc71;
            position: absolute;
            left: -20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .dos-donts-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <h1><?php echo ($lang == 'en') ? 'Disaster Do\'s and Don\'ts' : 'দুর্যোগের করণীয় ও বর্জনীয়'; ?></h1>
            <div class="language-switcher">
                <button class="language-btn <?php echo ($lang == 'en') ? 'active' : ''; ?>" onclick="window.location.href='?language=en'">English</button>
                <button class="language-btn <?php echo ($lang == 'bn') ? 'active' : ''; ?>" onclick="window.location.href='?language=bn'">বাংলা</button>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="disaster-nav">
            <?php foreach ($disasterData as $key => $disaster): ?>
                <button class="disaster-btn" data-target="<?php echo $key; ?>"><?php echo $disaster[$lang]['title']; ?></button>
            <?php endforeach; ?>
        </div>
        
        <?php foreach ($disasterData as $key => $disaster): ?>
            <div class="content-section" id="<?php echo $key; ?>-content">
                <h2><?php echo $disaster[$lang]['title']; ?></h2>
                <div class="dos-donts-container">
                    <div class="dos">
                        <h3><?php echo ($lang == 'en') ? 'Do\'s' : 'করণীয়'; ?></h3>
                        <ul>
                            <?php foreach ($disaster[$lang]['dos'] as $do): ?>
                                <li><?php echo $do; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="donts">
                        <h3><?php echo ($lang == 'en') ? 'Don\'ts' : 'বর্জনীয়'; ?></h3>
                        <ul>
                            <?php foreach ($disaster[$lang]['donts'] as $dont): ?>
                                <li><?php echo $dont; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set first disaster as active by default
            const firstDisaster = document.querySelector('.disaster-btn');
            if (firstDisaster) {
                firstDisaster.classList.add('active');
                const firstContent = document.getElementById(firstDisaster.dataset.target + '-content');
                if (firstContent) {
                    firstContent.classList.add('active');
                }
            }
            
            // Handle disaster button clicks
            const disasterBtns = document.querySelectorAll('.disaster-btn');
            disasterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons and content
                    disasterBtns.forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.content-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    const targetContent = document.getElementById(this.dataset.target + '-content');
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>