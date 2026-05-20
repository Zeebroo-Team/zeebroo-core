#include "MainWindow.h"
#include "RibbonWidget.h"

#include <QComboBox>
#include <QFrame>
#include <QGridLayout>
#include <QHBoxLayout>
#include <QLabel>
#include <QLineEdit>
#include <QPushButton>
#include <QRegularExpression>
#include <QScrollArea>
#include <QSlider>
#include <QStatusBar>
#include <QStyle>
#include <QString>
#include <QTextEdit>
#include <QtGlobal>
#include <QVBoxLayout>
#include <QWidget>

namespace {

constexpr int kDefaultZoomPercent = 120;

int wordCountPlain(const QTextEdit* ed)
{
    const QString text = ed->toPlainText();
    QString t = text;
    t.replace(QRegularExpression(QStringLiteral("\\s+")), QStringLiteral(" "));
    t = t.trimmed();
    if (t.isEmpty()) return 0;
    return static_cast<int>(t.split(QLatin1Char(' '), Qt::SkipEmptyParts).size());
}

QWidget* buildTitleChrome(MainWindow* ctx, QWidget* parent, QLabel** outDocTitle)
{
    auto* w = new QWidget(parent);
    w->setObjectName(QStringLiteral("wordTitleChrome"));

    auto* grid = new QGridLayout(w);
    grid->setContentsMargins(14, 7, 14, 7);
    grid->setHorizontalSpacing(10);
    grid->setVerticalSpacing(6);
    grid->setColumnStretch(0, 1);
    grid->setColumnStretch(1, 0);
    grid->setColumnStretch(2, 1);

    auto* left = new QWidget(w);
    left->setObjectName(QStringLiteral("wordTitleLeft"));
    auto* hl = new QHBoxLayout(left);
    hl->setContentsMargins(0, 0, 0, 0);
    hl->setSpacing(6);

    auto* autosave = new QLabel(left);
    autosave->setObjectName(QStringLiteral("wordAutoSaveLbl"));
    autosave->setText(ctx->tr("<b style=\"color:#484f5f\">AutoSave</b>"));
    autosave->setTextFormat(Qt::RichText);

    auto makeQat = [left](QStyle::StandardPixmap px, const QString& tip)
    {
        auto* btn = new QPushButton(left);
        btn->setObjectName(QStringLiteral("wordTitleBarBtn"));
        btn->setIcon(left->style()->standardIcon(px));
        btn->setIconSize(QSize(18, 18));
        btn->setFlat(true);
        btn->setFocusPolicy(Qt::NoFocus);
        btn->setToolTip(tip);
        btn->setFixedSize(32, 30);
        return btn;
    };

    hl->addWidget(autosave);
    hl->addWidget(makeQat(QStyle::SP_DirIcon, ctx->tr("Home")));
    hl->addWidget(makeQat(QStyle::SP_DialogSaveButton, ctx->tr("Save")));
    auto* undoT = makeQat(QStyle::SP_ArrowBack, ctx->tr("Undo"));
    undoT->setEnabled(false);
    auto* redoT = makeQat(QStyle::SP_ArrowForward, ctx->tr("Redo"));
    redoT->setEnabled(false);
    hl->addWidget(undoT);
    hl->addWidget(redoT);
    hl->addWidget(makeQat(QStyle::SP_ComputerIcon, ctx->tr("Print")));
    hl->addStretch(0);

    auto* title = new QLabel(w);
    title->setObjectName(QStringLiteral("wordDocTitle"));
    title->setAlignment(Qt::AlignCenter);

    auto* right = new QWidget(w);
    right->setObjectName(QStringLiteral("wordTitleRight"));
    auto* hr = new QHBoxLayout(right);
    hr->setContentsMargins(0, 0, 0, 0);
    hr->setSpacing(8);

    auto* search = new QLineEdit(right);
    search->setObjectName(QStringLiteral("wordSearchField"));
    search->setPlaceholderText(ctx->tr("Search (document)"));
    search->setClearButtonEnabled(true);
    search->setFixedWidth(200);

    auto* comments = new QPushButton(right);
    comments->setObjectName(QStringLiteral("wordNeutralBtn"));
    comments->setText(ctx->tr("Comments"));
    comments->setFocusPolicy(Qt::NoFocus);

    auto* editing = new QComboBox(right);
    editing->setObjectName(QStringLiteral("wordNeutralCombo"));
    editing->setFocusPolicy(Qt::NoFocus);
    editing->addItems({ctx->tr("Editing"), ctx->tr("Reviewing"), ctx->tr("Viewing")});

    auto* share = new QPushButton(right);
    share->setObjectName(QStringLiteral("wordShareBtn"));
    share->setText(ctx->tr("Share"));
    share->setFocusPolicy(Qt::NoFocus);
    share->setMinimumWidth(86);

    hr->addStretch(0);
    hr->addWidget(search, 0, Qt::AlignVCenter);
    hr->addWidget(comments, 0, Qt::AlignVCenter);
    hr->addWidget(editing, 0, Qt::AlignVCenter);
    hr->addWidget(share, 0, Qt::AlignVCenter);

    grid->addWidget(left, 0, 0, Qt::AlignLeft | Qt::AlignVCenter);
    grid->addWidget(title, 0, 1, Qt::AlignCenter);
    grid->addWidget(right, 0, 2, Qt::AlignRight | Qt::AlignVCenter);

    if (outDocTitle) {
        *outDocTitle = title;
    }
    return w;
}

QPair<QWidget*, QTextEdit*> buildWordWorkspace(QWidget* parent)
{
    auto* dock = new QWidget(parent);
    dock->setObjectName(QStringLiteral("wordWorkspace"));

    auto* scroll = new QScrollArea(dock);
    scroll->setObjectName(QStringLiteral("wordPageScroll"));
    scroll->setWidgetResizable(true);
    scroll->setFrameShape(QFrame::NoFrame);
    scroll->setHorizontalScrollBarPolicy(Qt::ScrollBarAsNeeded);

    auto* pageHost = new QWidget();
    pageHost->setObjectName(QStringLiteral("wordPageHost"));
    auto* phLay = new QVBoxLayout(pageHost);
    phLay->setContentsMargins(48, 32, 48, 72);
    phLay->addStretch(0);

    auto* page = new QFrame(pageHost);
    page->setObjectName(QStringLiteral("wordPaper"));
    auto* pv = new QVBoxLayout(page);
    pv->setContentsMargins(48, 48, 48, 56);

    auto* editor = new QTextEdit(page);
    editor->setObjectName(QStringLiteral("wordEditor"));
    editor->setFrameShape(QFrame::NoFrame);
    editor->setAcceptRichText(true);
    editor->setFontPointSize(11);
    editor->setHtml(QStringLiteral(
        "<p style=\"text-align:center;margin:0 0 8px 0;\">"
        "<span style=\"font-size:26pt;font-weight:700;\">Project Proposal</span></p>"
        "<p style=\"text-align:center;margin:0 0 18px 0;color:#444;\">"
        "<span style=\"font-size:14pt;\">Professional Website Development</span></p>"
        "<p style=\"text-align:center;margin:0;\">"
        "<span style=\"font-size:12pt;font-weight:700;\">Kash Garment (PVT) Ltd</span></p>"));

    pv->addWidget(editor);
    page->setFixedWidth(640);

    phLay->addWidget(page, 0, Qt::AlignHCenter | Qt::AlignTop);
    phLay->addStretch(1);

    scroll->setWidget(pageHost);

    auto* vl = new QVBoxLayout(dock);
    vl->setContentsMargins(0, 0, 0, 0);
    vl->setSpacing(0);
    vl->addWidget(scroll);

    return {dock, editor};
}

} // namespace

MainWindow::MainWindow(QWidget* parent)
    : QMainWindow(parent)
{
    applyOfficeLikeChrome();
    setWindowTitle(tr("Document1 — Zeebroo"));

    QWidget* chrome = buildTitleChrome(this, this, &m_docTitle);
    m_docTitle->setText(tr("Document1 — recovered"));

    m_ribbon = new RibbonWidget(this);

    QWidget* ws = nullptr;
    QTextEdit* ed = nullptr;
    const auto cw = buildWordWorkspace(this);
    ws = cw.first;
    ed = cw.second;
    m_editor = ed;

    QWidget* body = new QWidget(this);
    auto* bv = new QVBoxLayout(body);
    bv->setContentsMargins(0, 0, 0, 0);
    bv->setSpacing(0);
    bv->addWidget(chrome);
    bv->addWidget(m_ribbon);
    bv->addWidget(ws, 1);

    setCentralWidget(body);

    auto* pageLbl = new QLabel(tr("Page 1 of 6"));
    pageLbl->setObjectName(QStringLiteral("wordStatusLabel"));

    auto* wordLbl = new QLabel();
    wordLbl->setObjectName(QStringLiteral("wordStatusLabel"));
    wordLbl->setTextFormat(Qt::RichText);

    auto* accessibilityLbl = new QLabel(
        QStringLiteral("<span style=\"color:#0b5daa;font-weight:600;\">Accessibility: Investigate</span>"));
    accessibilityLbl->setTextFormat(Qt::RichText);

    statusBar()->addWidget(pageLbl);
    statusBar()->addPermanentWidget(wordLbl);

    accessibilityLbl->setContentsMargins(10, 0, 0, 0);

    wordLbl->setText(
        QStringLiteral("&nbsp;&nbsp;%1&nbsp;&nbsp;·&nbsp;&nbsp;<span style=\"color:#1a4782;\">&#x2698;</span>&nbsp;English")
            .arg(tr("%1 words").arg(wordCountPlain(m_editor))));

    auto* langWidget = new QWidget();
    auto* langLay = new QHBoxLayout(langWidget);
    langLay->setContentsMargins(0, 0, 0, 0);
    langLay->addWidget(accessibilityLbl);
    statusBar()->addPermanentWidget(langWidget);

    auto* zoomWrap = new QWidget();
    auto* zl = new QHBoxLayout(zoomWrap);
    zl->setContentsMargins(8, 0, 0, 0);
    zl->setSpacing(8);
    auto* zoomLab = new QLabel(QStringLiteral("120%"));
    zoomLab->setObjectName(QStringLiteral("wordStatusLabel"));
    zoomLab->setMinimumWidth(44);
    zoomLab->setAlignment(Qt::AlignRight | Qt::AlignVCenter);
    auto* zoom = new QSlider(Qt::Horizontal);
    zoom->setObjectName(QStringLiteral("wordZoomSlider"));
    zoom->setRange(80, 200);
    zoom->setValue(kDefaultZoomPercent);
    zoom->setFixedWidth(140);
    zl->addWidget(zoomLab, 0, Qt::AlignVCenter);
    zl->addWidget(zoom, 0, Qt::AlignVCenter);
    statusBar()->addPermanentWidget(zoomWrap);

    QObject::connect(m_editor, &QTextEdit::textChanged, this, [this, wordLbl]() {
        wordLbl->setText(
            QStringLiteral("&nbsp;&nbsp;%1&nbsp;&nbsp;·&nbsp;&nbsp;<span style=\"color:#1a4782;\">&#x2698;</span>&nbsp;English")
                .arg(tr("%1 words").arg(wordCountPlain(m_editor))));
    });

    QObject::connect(zoom, &QSlider::valueChanged, this, [zoomLab](int v) {
        zoomLab->setText(QString::number(v) + QLatin1Char('%'));
    });

    statusBar()->showMessage(tr("Ready"));
}

void MainWindow::applyOfficeLikeChrome()
{
#if defined(Q_OS_MACOS)
    setUnifiedTitleAndToolBarOnMac(false);
#else
    setUnifiedTitleAndToolBarOnMac(true);
#endif

    setStyleSheet(QStringLiteral(
        "QMainWindow { background:#f5f6f8; color:#2a2f36;}"
        "QWidget#wordTitleChrome { background:#ffffff; border-bottom:1px solid #eceef2;}"
        "QLabel#wordAutoSaveLbl { font-size:12px; color:#5c6470; padding:0 4px;}"
        "QLabel#wordDocTitle { font-size:13px; font-weight:600; color:#323842; min-width:180px;}"
        "QPushButton#wordTitleBarBtn { border:none; border-radius:6px; background:transparent;}"
        "QPushButton#wordTitleBarBtn:hover { background:#eceff4;}"
        "QLineEdit#wordSearchField { border:1px solid #d3d7de; border-radius:8px; padding:6px 10px;"
        "background:#f7f8fa; font-size:12px; min-height:18px;}"
        "QLineEdit#wordSearchField:focus { border-color:#0b5daa; background:#ffffff;}"
        "QPushButton#wordNeutralBtn { border:1px solid transparent; padding:8px 12px;"
        "border-radius:8px;background:transparent;color:#393f4d;font-weight:600;font-size:12px;}"
        "QPushButton#wordNeutralBtn:hover{background:#ebeff7;}"
        "QComboBox#wordNeutralCombo{min-height:26px;padding:4px 10px;border:none;"
        "background:transparent;color:#393f4d;font-weight:600;font-size:12px;}"
        "QComboBox#wordNeutralCombo:hover{background:#ebeff7;border-radius:8px;}"
        "QPushButton#wordShareBtn{background:#0d5fd8;color:#ffffff;border:none;border-radius:10px;"
        "padding:10px 16px;font-weight:680;font-size:13px;}"
        "QPushButton#wordShareBtn:hover{background:#094fa8;}"
        "QPushButton#wordShareBtn:pressed{background:#074080;}"
        "QWidget#ribbonWidgetRoot { background:#ffffff;}"
        "#ribbonChromeRow{background:#fafbfc;border-bottom:1px solid #cfd4db;}"
        "#ribbonTabBarHost{background:#fafbfc;}"
        "#ribbonPage{background:#fafbfc;border:none;border-radius:0;}"
        "QFrame#ribbonGroupDivider{background:#d6dbe4;margin:10px 1px 10px;"
        "min-height:94px;border:none;}"
        "#ribbonGroupPane{min-height:102px;background:transparent;padding:6px 0 8px;border:none;}"
        "QLabel#ribbonGroupTitle{font-size:11px;color:#69707c;letter-spacing:.08em;text-transform:"
        "uppercase;font-weight:750;padding:0;margin:0;margin-bottom:2px;}"
        "QComboBox#wordRibbonCombo{font-size:13px;color:#343a43;padding:3px 8px;"
        "min-height:24px;background:#fdfefe;border:1px solid #caced9;border-radius:6px;"
        "min-width:140px;} "
        "QComboBox#wordRibbonCombo::drop-down{background:transparent;border:none;width:26px;} "
        "QFontComboBox#wordRibbonFontCombo{font-size:13px;padding:3px 8px;min-height:24px;"
        "background:#fdfefe;border:1px solid #caced9;border-radius:6px;min-width:220px;} "
        "QFontComboBox#wordRibbonFontCombo::drop-down{background:transparent;border:none;width:26px;} "
        "QToolButton#ribbonSmallBtn{background:transparent;border:1px solid transparent;border-radius:6px;"
        "padding:3px;color:#343a43;min-height:26px;min-width:26px;} "
        "QToolButton#ribbonSmallBtn:hover{border-color:#cfd6e8;background:#eaf0fb;}"
        "QToolButton#ribbonMegaButton{background:#fdfefe;border:1px solid #d8deed;border-radius:8px;"
        "padding:6px;color:#343a43;}"
        "QToolButton#ribbonMegaButton:hover{border-color:#7c9bd4;background:#eaf0fb;}"
        "QToolButton{background:transparent;border:1px solid transparent;border-radius:6px;"
        "padding:3px;color:#343a43;min-height:28px;min-width:28px;}"
        "QToolButton:hover{border-color:#cfd6e8;background:#eaf0fb;}"
        "QPushButton#wordStyleThumb{background:#f3f6fc;border:1px solid #d3d9e8;border-radius:8px;"
        "padding:8px 12px;color:#343a43;text-align:left;min-width:120px;font-size:13px;}"
        "QPushButton#wordStyleThumb:hover{border-color:#0b5daa;background:#eaf0fb;}"
        "QPushButton#wordStylePreviewH1{font-size:17px;font-weight:800;color:#252a31;}"
        "QPushButton#wordStylePreviewH2{font-size:15px;font-weight:700;color:#252a31;}"
        "QTabBar#officeRibbonTabBar{background:transparent;border:none;}"
        "QTabBar#officeRibbonTabBar::tab{min-width:80px;padding:10px 16px 8px;margin-right:2px;"
        "background:transparent;border:none;border-bottom:2px solid transparent;"
        "color:#4b5361;font-weight:650;font-size:13px;}"
        "QTabBar#officeRibbonTabBar::tab:selected{color:#0b5daa;border-bottom:2px solid #0b5daa;}"
        "QTabBar#officeRibbonTabBar::tab:!selected:hover{background:#eceff4;border-radius:6px 6px 0 0;}"
        "QWidget#wordWorkspace{background:#c8cbd3;}"
        "QScrollArea#wordPageScroll{background:#c8cbd3;border:none;}"
        "QWidget#wordPageHost{background:#c8cbd3;}"
        "QFrame#wordPaper{background:#ffffff;border:1px solid #b8bec9;border-radius:1px;}"
        "QTextEdit#wordEditor{background:#ffffff;color:#1b1f27;font-size:11pt;}"
        "QStatusBar{background:#f1f2f5;border-top:1px solid #d5d8df;font-size:12px;padding:4px 8px;}"
        "QLabel#wordStatusLabel{color:#4b5361;padding:0 4px;}"
        "QSlider#wordZoomSlider::groove:horizontal{height:4px;background:#d5d8df;border-radius:2px;}"
        "QSlider#wordZoomSlider::handle:horizontal{width:12px;height:12px;margin:-5px 0;"
        "background:#0b5daa;border-radius:6px;}"));
}
