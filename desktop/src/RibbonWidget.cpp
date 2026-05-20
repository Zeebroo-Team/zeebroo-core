#include "RibbonWidget.h"

#include <QAction>
#include <QComboBox>
#include <QFontComboBox>
#include <QFrame>
#include <QHBoxLayout>
#include <QLabel>
#include <QIcon>
#include <QPushButton>
#include <QSizePolicy>
#include <QStackedWidget>
#include <QStyle>
#include <QTabBar>
#include <QToolButton>
#include <QVBoxLayout>
#include <utility>

namespace {

std::pair<QWidget*, QHBoxLayout*> ribbonGroup(QWidget* parent, const QString& title)
{
    auto* group = new QWidget(parent);
    group->setObjectName(QStringLiteral("ribbonGroupPane"));
    auto* vl = new QVBoxLayout(group);
    vl->setSpacing(5);
    vl->setContentsMargins(4, 2, 12, 4);

    auto* cap = new QLabel(title, group);
    cap->setObjectName(QStringLiteral("ribbonGroupTitle"));
    vl->addWidget(cap);

    auto* hz = new QHBoxLayout();
    hz->setSpacing(5);
    hz->setContentsMargins(0, 0, 0, 0);
    vl->addLayout(hz);
    vl->addStretch(1);
    hz->setAlignment(Qt::AlignLeft | Qt::AlignBottom);

    return {group, hz};
}

QFrame* ribbonGroupDivider(QWidget* parent)
{
    auto* line = new QFrame(parent);
    line->setObjectName(QStringLiteral("ribbonGroupDivider"));
    line->setFrameShape(QFrame::NoFrame);
    line->setFixedWidth(1);
    line->setMinimumHeight(96);
    return line;
}

} // namespace

RibbonWidget::RibbonWidget(QWidget* parent)
    : QWidget(parent)
{
    setObjectName(QStringLiteral("ribbonWidgetRoot"));

    m_tabs = new QTabBar(this);
    m_tabs->setObjectName(QStringLiteral("officeRibbonTabBar"));
    m_tabs->setDrawBase(false);
    m_tabs->setExpanding(false);
    m_tabs->setDocumentMode(true);
    m_tabs->setShape(QTabBar::RoundedNorth);
    m_tabs->setUsesScrollButtons(true);
    m_tabs->setElideMode(Qt::ElideRight);
    m_tabs->addTab(tr("Home"));
    m_tabs->addTab(tr("Insert"));
    m_tabs->addTab(tr("Draw"));
    m_tabs->addTab(tr("Layout"));
    m_tabs->setFocusPolicy(Qt::NoFocus);

    QSizePolicy tabsPolicy(QSizePolicy::Preferred, QSizePolicy::Fixed);
    m_tabs->setSizePolicy(tabsPolicy);

    auto* tabRow = new QWidget(this);
    tabRow->setObjectName(QStringLiteral("ribbonChromeRow"));
    auto* trLay = new QHBoxLayout(tabRow);
    trLay->setContentsMargins(10, 2, 16, 0);
    trLay->setSpacing(0);
    trLay->addWidget(m_tabs, 0, Qt::AlignLeft | Qt::AlignBottom);
    trLay->addStretch(1);

    m_stack = new QStackedWidget(this);
    m_stack->setMinimumHeight(132);
    m_stack->addWidget(buildHomePage());
    m_stack->addWidget(buildInsertPage());
    m_stack->addWidget(buildDrawPage());
    m_stack->addWidget(buildLayoutPage());

    QObject::connect(m_tabs, &QTabBar::currentChanged, m_stack, &QStackedWidget::setCurrentIndex);

    auto* root = new QVBoxLayout(this);
    root->setSpacing(0);
    root->setContentsMargins(0, 0, 0, 0);
    root->addWidget(tabRow);
    root->addWidget(m_stack);
}

QToolButton* RibbonWidget::makeGlyphButton(const QString& glyph, const QString& tip, QWidget* parent)
{
    auto* b = new QToolButton(parent);
    b->setObjectName(QStringLiteral("ribbonSmallBtn"));
    b->setText(glyph);
    b->setToolTip(tip);
    b->setFocusPolicy(Qt::NoFocus);
    b->setFixedSize(28, 26);
    return b;
}

QToolButton* RibbonWidget::makeSmallIconButton(const QIcon& icon, const QString& tip, QWidget* parent)
{
    auto* b = new QToolButton(parent);
    b->setObjectName(QStringLiteral("ribbonSmallBtn"));
    b->setIcon(icon);
    b->setIconSize(QSize(18, 18));
    b->setToolButtonStyle(Qt::ToolButtonIconOnly);
    b->setToolTip(tip);
    b->setFocusPolicy(Qt::NoFocus);
    b->setFixedSize(30, 26);
    return b;
}

QWidget* RibbonWidget::buildFontBlock(QWidget* row)
{
    auto* box = new QWidget(row);
    box->setObjectName(QStringLiteral("ribbonGroupPane"));
    auto* vl = new QVBoxLayout(box);
    vl->setSpacing(5);
    vl->setContentsMargins(4, 2, 10, 4);

    auto* cap = new QLabel(tr("Font"), box);
    cap->setObjectName(QStringLiteral("ribbonGroupTitle"));
    vl->addWidget(cap);

    auto* r1 = new QHBoxLayout();
    r1->setSpacing(6);
    auto* fc = new QFontComboBox(box);
    fc->setObjectName(QStringLiteral("wordRibbonFontCombo"));
    fc->setMaxVisibleItems(9);
    auto* sizes = new QComboBox(box);
    sizes->setObjectName(QStringLiteral("wordRibbonCombo"));
    static const char* pts[] = {"8",  "9",  "10", "11", "12", "14", "16", "18", "20",
                                "22", "24", "26", "28", "36", "48", "72"};
    for (const char* p : pts) {
        sizes->addItem(QString::fromLatin1(p));
    }
    sizes->setCurrentText(QStringLiteral("11"));
    sizes->setFixedWidth(58);
    r1->addWidget(fc, 1);
    r1->addWidget(sizes, 0);
    vl->addLayout(r1);

    auto* r2 = new QHBoxLayout();
    r2->setSpacing(2);
    auto* b = makeGlyphButton(tr("B"), tr("Bold"), box);
    QFont bf = b->font();
    bf.setBold(true);
    b->setFont(bf);
    auto* i = makeGlyphButton(tr("I"), tr("Italic"), box);
    QFont it = i->font();
    it.setItalic(true);
    i->setFont(it);
    auto* u = makeGlyphButton(tr("U"), tr("Underline"), box);
    QFont uf = u->font();
    uf.setUnderline(true);
    u->setFont(uf);

    r2->addWidget(b);
    r2->addWidget(i);
    r2->addWidget(u);
    r2->addWidget(makeGlyphButton(tr("abc"), tr("Strikethrough"), box));

    auto* sub = makeGlyphButton(QStringLiteral("X\u2082"), tr("Subscript"), box);
    sub->setFixedWidth(34);
    r2->addWidget(sub);

    auto* sup = makeGlyphButton(QStringLiteral("X\u00b2"), tr("Superscript"), box);
    sup->setFixedWidth(34);
    r2->addWidget(sup);

    r2->addWidget(makeGlyphButton(QStringLiteral("A"), tr("Text Effects and Typography"), box));
    r2->addWidget(makeSmallIconButton(
        QIcon::fromTheme(QStringLiteral("format-text-highlight"),
                         style()->standardIcon(QStyle::SP_DriveCDIcon)),
        tr("Text highlight color"),
        box));
    r2->addWidget(makeSmallIconButton(
        QIcon::fromTheme(QStringLiteral("format-stroke-color"),
                         style()->standardIcon(QStyle::SP_CommandLink)),
        tr("Font color"),
        box));
    r2->addStretch(0);
    vl->addLayout(r2);
    vl->addStretch(0);
    return box;
}

QWidget* RibbonWidget::buildParagraphBlock(QWidget* row)
{
    auto* box = new QWidget(row);
    box->setObjectName(QStringLiteral("ribbonGroupPane"));
    auto* vl = new QVBoxLayout(box);
    vl->setSpacing(5);
    vl->setContentsMargins(4, 2, 10, 4);

    auto* cap = new QLabel(tr("Paragraph"), box);
    cap->setObjectName(QStringLiteral("ribbonGroupTitle"));
    vl->addWidget(cap);

    auto* r1 = new QHBoxLayout();
    r1->setSpacing(2);
    r1->addWidget(makeGlyphButton(QStringLiteral("\u2022"), tr("Bullets"), box));
    r1->addWidget(makeGlyphButton(QStringLiteral("1."), tr("Numbering"), box));
    r1->addWidget(makeGlyphButton(QStringLiteral("\u2630"), tr("Multilevel list"), box));
    r1->addWidget(makeSmallIconButton(style()->standardIcon(QStyle::SP_ArrowBack), tr("Decrease indent"), box));
    r1->addWidget(makeSmallIconButton(style()->standardIcon(QStyle::SP_ArrowForward), tr("Increase indent"), box));
    r1->addStretch(0);
    vl->addLayout(r1);

    auto* r2 = new QHBoxLayout();
    r2->setSpacing(2);
    r2->addWidget(makeGlyphButton(QStringLiteral("L"), tr("Align left"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("C"), tr("Align center"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("R"), tr("Align right"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("J"), tr("Justify"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("\u2630"), tr("Line spacing"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("\u2588"), tr("Shading"), box));
    r2->addWidget(makeGlyphButton(QStringLiteral("\u2293"), tr("Borders"), box));
    r2->addStretch(0);
    vl->addLayout(r2);
    vl->addStretch(0);
    return box;
}

QWidget* RibbonWidget::buildStylesBlock(QWidget* row)
{
    auto* box = new QWidget(row);
    box->setObjectName(QStringLiteral("ribbonGroupPane"));
    auto* vl = new QVBoxLayout(box);
    vl->setSpacing(5);
    vl->setContentsMargins(4, 2, 8, 4);

    auto* cap = new QLabel(tr("Styles"), box);
    cap->setObjectName(QStringLiteral("ribbonGroupTitle"));
    vl->addWidget(cap);

    auto* hz = new QHBoxLayout();
    hz->setSpacing(6);
    auto addThumb = [&](const QString& txt, const QString& tip, const QString& obj = QString()) {
        auto* pb = new QPushButton(txt, box);
        pb->setToolTip(tip);
        pb->setObjectName(obj.isEmpty() ? QStringLiteral("wordStyleThumb") : obj);
        pb->setFocusPolicy(Qt::NoFocus);
        hz->addWidget(pb);
    };
    addThumb(tr("Normal"), tr("Normal"));
    addThumb(tr("No Spacing"), tr("No Spacing"));
    addThumb(tr("Heading 1"), tr("Heading 1"), QStringLiteral("wordStylePreviewH1"));
    addThumb(tr("Heading 2"), tr("Heading 2"), QStringLiteral("wordStylePreviewH2"));
    auto* pane = new QPushButton(tr("Styles\nPane"), box);
    pane->setObjectName(QStringLiteral("wordStyleThumb"));
    pane->setFocusPolicy(Qt::NoFocus);
    hz->addWidget(pane);
    hz->addStretch(0);
    vl->addLayout(hz);
    vl->addStretch(0);
    return box;
}

QWidget* RibbonWidget::buildVoiceBlock(QWidget* row)
{
    auto* box = new QWidget(row);
    box->setObjectName(QStringLiteral("ribbonGroupPane"));
    auto* vl = new QVBoxLayout(box);
    vl->setSpacing(5);
    vl->setContentsMargins(4, 2, 8, 4);

    auto* cap = new QLabel(tr("Voice & Add-ins"), box);
    cap->setObjectName(QStringLiteral("ribbonGroupTitle"));
    vl->addWidget(cap);

    auto* hz = new QHBoxLayout();
    hz->setSpacing(4);
    hz->addWidget(makeSmallIconButton(
        QIcon::fromTheme(QStringLiteral("audio-input-microphone"),
                         style()->standardIcon(QStyle::SP_CommandLink)),
        tr("Dictate"),
        box));
    hz->addWidget(makeGlyphButton(QStringLiteral("\u26a1"), tr("Sensitivity"), box));
    hz->addWidget(makeGlyphButton(QStringLiteral("\u229e"), tr("Add-ins"), box));
    hz->addWidget(makeGlyphButton(QStringLiteral("\u2713"), tr("Editor"), box));
    auto* copilot = new QPushButton(tr("Copilot"), box);
    copilot->setObjectName(QStringLiteral("wordStyleThumb"));
    copilot->setFocusPolicy(Qt::NoFocus);
    copilot->setMinimumWidth(88);
    hz->addWidget(copilot);
    hz->addStretch(0);
    vl->addLayout(hz);
    vl->addStretch(0);
    return box;
}

QWidget* RibbonWidget::buildHomePage()
{
    auto* row = new QWidget();
    row->setObjectName(QStringLiteral("ribbonPage"));

    auto* layout = new QHBoxLayout(row);
    layout->setSpacing(2);
    layout->setContentsMargins(8, 4, 10, 6);
    layout->setAlignment(Qt::AlignLeft | Qt::AlignBottom);

    auto [clip, clipLay] = ribbonGroup(row, tr("Clipboard"));
    QAction* paste = new QAction(
        QIcon::fromTheme(QStringLiteral("edit-paste"), style()->standardIcon(QStyle::SP_FileDialogNewFolder)),
        tr("Paste"),
        clip);
    QToolButton* pasteBtn = makeRibbonButton(paste);
    pasteBtn->setObjectName(QStringLiteral("ribbonMegaButton"));
    pasteBtn->setToolButtonStyle(Qt::ToolButtonTextUnderIcon);
    pasteBtn->setPopupMode(QToolButton::DelayedPopup);
    pasteBtn->setMinimumSize(52, 66);
    pasteBtn->setIconSize(QSize(26, 26));

    QAction* cut = new QAction(
        QIcon::fromTheme(QStringLiteral("edit-cut"), style()->standardIcon(QStyle::SP_DialogDiscardButton)),
        tr("Cut"),
        clip);
    QAction* copy = new QAction(
        QIcon::fromTheme(QStringLiteral("edit-copy"), style()->standardIcon(QStyle::SP_FileIcon)),
        tr("Copy"),
        clip);
    QAction* painter = new QAction(
        QIcon::fromTheme(QStringLiteral("format-paint-brush"), style()->standardIcon(QStyle::SP_FileDialogListView)),
        tr("Format Painter"),
        clip);

    auto* ccWrap = new QWidget(clip);
    auto* ccCol = new QVBoxLayout(ccWrap);
    ccCol->setSpacing(3);
    ccCol->setContentsMargins(0, 0, 0, 0);
    ccCol->addStretch(1);
    auto* cutBtn = makeRibbonButton(cut);
    auto* copyBtn = makeRibbonButton(copy);
    auto* paintBtn = makeRibbonButton(painter);
    cutBtn->setFixedHeight(27);
    copyBtn->setFixedHeight(27);
    paintBtn->setFixedHeight(27);
    ccCol->addWidget(cutBtn);
    ccCol->addWidget(copyBtn);
    ccCol->addWidget(paintBtn);

    clipLay->setAlignment(Qt::AlignBottom);
    clipLay->addWidget(pasteBtn, 0, Qt::AlignBottom);
    clipLay->addWidget(ccWrap, 0, Qt::AlignBottom);
    layout->addWidget(clip);
    layout->addWidget(ribbonGroupDivider(row));

    layout->addWidget(buildFontBlock(row));
    layout->addWidget(ribbonGroupDivider(row));

    layout->addWidget(buildParagraphBlock(row));
    layout->addWidget(ribbonGroupDivider(row));

    layout->addWidget(buildStylesBlock(row));
    layout->addWidget(ribbonGroupDivider(row));

    layout->addWidget(buildVoiceBlock(row));
    layout->addStretch(1);

    return row;
}

QWidget* RibbonWidget::buildInsertPage()
{
    auto* row = new QWidget();
    row->setObjectName(QStringLiteral("ribbonPage"));

    auto* layout = new QHBoxLayout(row);
    layout->setSpacing(2);
    layout->setContentsMargins(8, 4, 10, 6);
    layout->setAlignment(Qt::AlignLeft | Qt::AlignBottom);

    auto [pages, pLay] = ribbonGroup(row, tr("Pages"));
    pLay->addWidget(makeRibbonButton(new QAction(style()->standardIcon(QStyle::SP_FileIcon), tr("Cover Page"), pages)));
    pLay->addWidget(makeRibbonButton(new QAction(style()->standardIcon(QStyle::SP_FileDialogNewFolder), tr("Blank Page"), pages)));
    pLay->addWidget(makeRibbonButton(new QAction(style()->standardIcon(QStyle::SP_ArrowDown), tr("Breaks"), pages)));
    layout->addWidget(pages);
    layout->addWidget(ribbonGroupDivider(row));

    auto [tables, tLay] = ribbonGroup(row, tr("Tables"));
    tLay->addWidget(makeRibbonButton(new QAction(tr("Table"), tables)));
    tLay->addWidget(makeRibbonButton(new QAction(tr("Excel Spreadsheet"), tables)));
    layout->addWidget(tables);
    layout->addWidget(ribbonGroupDivider(row));

    auto [illus, iLay] = ribbonGroup(row, tr("Illustrations"));
    iLay->addWidget(makeRibbonButton(new QAction(tr("Pictures"), illus)));
    iLay->addWidget(makeRibbonButton(new QAction(tr("Shapes"), illus)));
    iLay->addWidget(makeRibbonButton(new QAction(tr("Icons"), illus)));
    layout->addWidget(illus);

    layout->addStretch(1);
    return row;
}

QWidget* RibbonWidget::buildDrawPage()
{
    auto* row = new QWidget();
    row->setObjectName(QStringLiteral("ribbonPage"));

    auto* layout = new QHBoxLayout(row);
    layout->setSpacing(2);
    layout->setContentsMargins(8, 4, 10, 6);

    auto [tools, tl] = ribbonGroup(row, tr("Drawing Tools"));
    tl->addWidget(makeRibbonButton(new QAction(tr("Pen"), tools)));
    tl->addWidget(makeRibbonButton(new QAction(tr("Highlighter"), tools)));
    tl->addWidget(makeRibbonButton(new QAction(tr("Lasso Select"), tools)));
    layout->addWidget(tools);
    layout->addWidget(ribbonGroupDivider(row));

    auto [cvt, cl] = ribbonGroup(row, tr("Convert"));
    cl->addWidget(makeRibbonButton(new QAction(tr("Ink to Shape"), cvt)));
    cl->addWidget(makeRibbonButton(new QAction(tr("Ink to Math"), cvt)));
    layout->addWidget(cvt);

    layout->addStretch(1);
    return row;
}

QWidget* RibbonWidget::buildLayoutPage()
{
    auto* row = new QWidget();
    row->setObjectName(QStringLiteral("ribbonPage"));

    auto* layout = new QHBoxLayout(row);
    layout->setSpacing(2);
    layout->setContentsMargins(8, 4, 10, 6);
    layout->setAlignment(Qt::AlignLeft | Qt::AlignBottom);

    auto [pageSetup, lay] = ribbonGroup(row, tr("Page setup"));
    lay->addWidget(makeRibbonButton(new QAction(tr("Margins"), pageSetup)));
    lay->addWidget(makeRibbonButton(new QAction(tr("Orientation"), pageSetup)));
    lay->addWidget(makeRibbonButton(new QAction(tr("Size"), pageSetup)));
    lay->addWidget(makeRibbonButton(new QAction(tr("Columns"), pageSetup)));
    layout->addWidget(pageSetup);
    layout->addWidget(ribbonGroupDivider(row));

    auto [breaks, bl] = ribbonGroup(row, tr("Breaks"));
    bl->addWidget(makeRibbonButton(new QAction(tr("Page breaks"), breaks)));
    bl->addWidget(makeRibbonButton(new QAction(tr("Line numbers"), breaks)));
    layout->addWidget(breaks);

    layout->addStretch(1);
    return row;
}

QToolButton* RibbonWidget::makeRibbonButton(QAction* action)
{
    auto* btn = new QToolButton();
    btn->setDefaultAction(action);
    btn->setAutoRaise(false);
    btn->setToolButtonStyle(Qt::ToolButtonIconOnly);
    btn->setIconSize(QSize(21, 21));
    btn->setMinimumSize(34, 28);
    btn->setFocusPolicy(Qt::NoFocus);
    return btn;
}
