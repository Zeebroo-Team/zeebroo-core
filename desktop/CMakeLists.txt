cmake_minimum_required(VERSION 3.16)
project(zeebroo-desktop LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_AUTOMOC ON)
set(CMAKE_AUTORCC ON)
set(CMAKE_AUTOUIC ON)

find_package(Qt6 REQUIRED COMPONENTS Widgets)

qt_add_executable(zeebroo-desktop
    src/main.cpp
    src/MainWindow.h
    src/MainWindow.cpp
    src/RibbonWidget.h
    src/RibbonWidget.cpp
)

target_link_libraries(zeebroo-desktop PRIVATE Qt6::Widgets)

if (APPLE)
    set_target_properties(zeebroo-desktop PROPERTIES MACOSX_BUNDLE TRUE)
endif()
